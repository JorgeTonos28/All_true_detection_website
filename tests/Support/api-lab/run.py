"""Real loopback HTTP checks against an isolated Laravel database (stdlib only)."""
from __future__ import annotations

import argparse
import concurrent.futures
import hashlib
import hmac
import io
import json
import os
from pathlib import Path
import re
import socket
import struct
import subprocess
import threading
import time
import urllib.error
import urllib.request
import uuid
import zipfile
import zlib

BASE = Path(__file__).resolve().parents[3]
SUPPORT = BASE / "tests/Support/api-lab"


def pdf(label: str) -> bytes:
    stream = f"BT /F1 12 Tf 30 100 Td ({label}) Tj ET".encode("ascii")
    objects = [b"<< /Type /Catalog /Pages 2 0 R >>", b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
               b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 150] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
               b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
               b"<< /Length " + str(len(stream)).encode() + b" >>\nstream\n" + stream + b"\nendstream"]
    result, offsets = b"%PDF-1.4\n", [0]
    for index, obj in enumerate(objects, 1):
        offsets.append(len(result))
        result += f"{index} 0 obj\n".encode() + obj + b"\nendobj\n"
    start = len(result)
    result += b"xref\n0 6\n0000000000 65535 f \n" + b"".join(f"{o:010} 00000 n \n".encode() for o in offsets[1:])
    return result + f"trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{start}\n%%EOF\n".encode()


def png(red=20, width=40, height=30) -> bytes:
    def chunk(kind, data):
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data) & 0xFFFFFFFF)
    raw = (b"\0" + bytes([red, 80, 120]) * width) * height
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b"")


def docx(macro=False) -> bytes:
    data = io.BytesIO()
    with zipfile.ZipFile(data, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr("[Content_Types].xml", '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
        archive.writestr("word/document.xml", '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>SYNTHETIC API TEST - NO REAL DOCUMENT</w:t></w:r></w:p></w:body></w:document>')
        if macro:
            archive.writestr("word/vbaProject.bin", b"synthetic forbidden macro container")
    return data.getvalue()


def profile(codes, number="00000000000"):
    data = {"first_name": "Persona", "last_name": "Ficticia", "service_codes": codes, "authorization_confirmed": True}
    if set(codes) & {"university-degree", "exequatur", "identity-verification", "bailiff-credentials"}:
        data.update(identity_number=number, address="Direccion ficticia para pruebas", purpose="Prueba autorizada de integracion")
    if "home-visits" in codes:
        data["address"] = "Direccion ficticia para pruebas"
    if "university-degree" in codes:
        data["degree"] = {"institution": "Institucion ficticia", "degree_name": "Documento de prueba", "graduation_year": 2020}
    if "exequatur" in codes:
        data["exequatur"] = {"profession": "Profesion ficticia", "number": "TEST-001"}
    return data


def order_payload(codes, people=None):
    if people is None:
        personal = [c for c in codes if c != "training-equipment"]
        people = [profile(personal)] if personal else []
    return {"subject": "Ensayo API sin datos reales", "consent": True,
            "items": [{"service_code": c, "quantity": sum(c in p["service_codes"] for p in people) if c != "training-equipment" else 1} for c in codes],
            **({"people": people} if people else {})}


class LabRun:
    def __init__(self, *, provider="mock", root=None):
        self.root = Path(root).resolve() if root else (BASE / "storage/app/private/api-lab" / ("http-" + uuid.uuid4().hex[:12])).resolve()
        self.env = dict(os.environ, ATD_API_LAB_ROOT=str(self.root), ATD_API_LAB_PROVIDER=provider)
        self.processes, self.logs, self.results = [], [], []
        self.tokens = {}
        self.ports = []
        self.upload_count = 0
        self.http_count = 0
        self.count_lock = threading.Lock()

    def control(self, action=None, **data):
        command = ["php", str(SUPPORT / "control.php"), "init" if action is None else "op"]
        result = subprocess.run(command, cwd=BASE, env=self.env, input=json.dumps({"action": action, **data}), text=True, encoding="utf-8", capture_output=True, timeout=90)
        try:
            return json.loads(result.stdout)
        except ValueError as error:
            raise RuntimeError("El control del laboratorio no devolvio JSON; revisar su log privado") from error

    def check(self, name, condition, **evidence):
        self.results.append({"name": name, "passed": bool(condition), **evidence})
        if not condition:
            print("FAIL: " + name, flush=True)

    def start(self, *, resume=False):
        if not resume:
            result = self.control()
            if not result.get("ok"):
                raise RuntimeError("No se pudo inicializar la base aislada: " + json.dumps(result))
        self.manifest = json.loads((self.root / "manifest.json").read_text(encoding="utf-8"))
        self.tokens = self.manifest["tokens"]
        (self.root / "fixtures/result.pdf").write_bytes(pdf("SYNTHETIC RESULT"))
        for _ in range(2):
            with socket.socket() as listener:
                listener.bind(("127.0.0.1", 0))
                port = listener.getsockname()[1]
            log = open(self.root / f"http-{port}.log", "wb")
            command = ["php", "-d", "upload_max_filesize=10M", "-d", "post_max_size=24M", "-d", "max_file_uploads=20", "-S", f"127.0.0.1:{port}", str(SUPPORT / "router.php")]
            process = subprocess.Popen(command, cwd=BASE, env=self.env, stdout=log, stderr=log, creationflags=0x08000000 if os.name == "nt" else 0)
            self.processes.append(process)
            self.logs.append(log)
            self.ports.append(port)
            for _ in range(80):
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=.2):
                        break
                except OSError:
                    time.sleep(.1)

    def request(self, name, method, path, *, body=None, raw=None, fields=None, file=None, token="client", headers=None, expect=200, port=None, record=True):
        with self.count_lock:
            self.http_count += 1
        header = {"Accept": "application/json"}
        if token:
            header["Authorization"] = "Bearer " + self.tokens.get(token, token)
        if body is not None:
            raw = json.dumps(body, ensure_ascii=False).encode()
            header["Content-Type"] = "application/json"
        if file is not None or fields is not None:
            boundary = "ATDLab" + uuid.uuid4().hex
            pieces = []
            for key, value in (fields or {}).items():
                pieces.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            if file:
                filename, content_type, content = file
                pieces.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: {content_type}\r\n\r\n'.encode(), content, b"\r\n"])
            pieces.append(f"--{boundary}--\r\n".encode())
            raw = b"".join(pieces)
            header["Content-Type"] = "multipart/form-data; boundary=" + boundary
        header.update(headers or {})
        started = time.monotonic()
        request = urllib.request.Request(f"http://127.0.0.1:{port or self.ports[0]}{path}", data=raw, method=method, headers=header)
        try:
            response = urllib.request.urlopen(request, timeout=45)
        except urllib.error.HTTPError as error:
            response = error
        content, status, response_headers = response.read(), response.status, dict(response.headers)
        try:
            data = json.loads(content)
        except (ValueError, UnicodeDecodeError):
            data = {}
        allowed = expect if isinstance(expect, (tuple, list)) else [expect]
        if record:
            self.check(name, status in allowed, status=status, expected=list(allowed), ms=round((time.monotonic() - started) * 1000, 1), method=method,
                       path=path.split("?")[0])
            if path.startswith("/api/v1"):
                self.check(name + " / correlation", bool(response_headers.get("X-Request-ID")))
            if status >= 400:
                self.check(name + " / error envelope", isinstance(data.get("error"), dict) and isinstance(data.get("meta"), dict))
                self.check(name + " / no trace or secret", all(t.encode() not in content for t in self.tokens.values()) and b"Stack trace" not in content and b"vendor/laravel" not in content)
        return status, data, content, response_headers

    def create(self, codes, *, payload=None, token="client", key=None, expect=201, name=None):
        key = key or "lab-" + uuid.uuid4().hex
        result = self.request(name or ("create " + "+".join(codes)), "POST", "/api/v1/orders", body=payload or order_payload(codes), token=token, headers={"Idempotency-Key": key}, expect=expect)
        return result[1].get("data", {}), key

    def upload(self, order, person, purpose, content, filename="evidence.pdf", content_type="application/pdf", expect=201, key=None):
        # Validation scenarios are independent of quota scenarios. Reset only this
        # disposable lab's cache; dedicated burst checks exercise the real limiter.
        if self.upload_count % 7 == 0:
            self.control("clear-cache")
        self.upload_count += 1
        return self.request("upload " + purpose + " / " + filename, "POST", f'/api/v1/orders/{order}/attachments', fields={"purpose": purpose, **({"person_id": person} if person else {})},
                            file=(filename, content_type, content), headers={"Idempotency-Key": key or "doc-" + uuid.uuid4().hex}, expect=expect)

    def auth_and_contract(self):
        for token, status in [(None, 401), ("not-a-token", 401), ("expired", 401), ("inactive", 403), ("unverified", 403), ("staff", 403)]:
            self.request("auth " + str(token), "GET", "/api/v1/services", token=token, expect=status)
        self.request("write token cannot read", "GET", "/api/v1/services", token="orders:write", expect=403)
        self.request("token in URL rejected", "GET", "/api/v1/services?token=synthetic-ignored", token=None, expect=401)
        self.request("wrong content type", "POST", "/api/v1/orders", raw=b"subject=x", headers={"Content-Type": "application/x-www-form-urlencoded"}, expect=415)
        self.request("malformed JSON", "POST", "/api/v1/orders", raw=b'{"subject":', headers={"Content-Type": "application/json"}, expect=400)
        for raw in [b"null", b"[]", b'"scalar"', b"123"]:
            self.request("non-object JSON " + raw.decode(), "POST", "/api/v1/orders", raw=raw, headers={"Content-Type": "application/json", "Idempotency-Key": "bad-json-" + uuid.uuid4().hex}, expect=422)
        self.request("oversized JSON", "POST", "/api/v1/orders", raw=b'{"details":"' + b"x" * 1048576 + b'"}', headers={"Content-Type": "application/json"}, expect=413)
        for query in ["per_page=101", "per_page=-1", "per_page[]=1", "status=invalid", "status[]=submitted"]:
            self.request("invalid listing " + query, "GET", "/api/v1/orders?" + query, expect=422)
        self.request("unknown order", "GET", "/api/v1/orders/01AAAAAAAAAAAAAAAAAAAAAAAA", expect=404)
        self.request("order listing", "GET", "/api/v1/orders?per_page=1")
        self.request("invoice listing", "GET", "/api/v1/invoices?status=unpaid", token="invoices:read")
        for key in ["short", "contains spaces", "x" * 129]:
            self.create(["training-equipment"], key=key, expect=422, name="invalid idempotency key")

    def catalog_and_services(self):
        _, catalog, _, _ = self.request("catalog", "GET", "/api/v1/services")
        self.check("all 11 active services advertised", len(catalog.get("data", [])) == 11)
        self.check("retired services absent", not {"deception-detection", "applied-ai", "investigations"} & {s["code"] for s in catalog.get("data", [])})
        self.control("mode", mode="manual")
        for index, service in enumerate(catalog["data"]):
            print("SERVICE: " + service["code"], flush=True)
            self.control("clear-cache")
            code = service["code"]
            if code == "polygraph":
                people = [dict(profile([code]), service_options={code: option}) for option in ("pre_employment", "routine", "pre_employment")]
                payload = order_payload([code], people)
            elif code == "remote-interview-rppg":
                self.check("interview catalog requires a person but no capture", service["per_person"]
                           and not service["requires_identity_number"] and service["required_evidence"] == []
                           and service["identity_mode"] is None)
                payload = order_payload([code], [profile([code]), profile([code])])
                without_people = {k: v for k, v in payload.items() if k != "people"}
                self.create([code], payload=without_people, expect=422, name="interview requires authorized participants")
            else:
                payload = order_payload([code])
            created, key = self.create([code], payload=payload)
            if not created:
                continue
            order, person = created["id"], (created.get("people") or [{}])[0].get("id")
            again, _ = self.create([code], payload=payload, key=key, name="idempotent retry " + code)
            self.check("same order on retry " + code, order == again.get("id"))
            altered = dict(payload, subject="Changed payload")
            self.create([code], payload=altered, key=key, expect=409, name="idempotency conflict " + code)
            required = service["required_evidence"]
            for position, purpose in enumerate(required):
                content = png(20 + index) if purpose == "selfie" else pdf(f"SYNTHETIC {code} {purpose}")
                self.upload(order, person, purpose, content, "selfie.png" if purpose == "selfie" else purpose + ".pdf", "image/png" if purpose == "selfie" else "application/pdf")
            supporting = self.upload(order, person, "supporting", docx(), "support.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
            attachment = supporting[1].get("data", {})
            root = f"/api/v1/orders/{order}"
            self.request("attachments list " + code, "GET", root + "/attachments")
            if attachment:
                _, _, binary, headers = self.request("attachment download " + code, "GET", root + "/attachments/" + attachment["id"] + "/download")
                self.check("attachment hash " + code, hashlib.sha256(binary).hexdigest() == attachment["sha256"])
                self.request("non-raster preview blocked " + code, "GET", root + "/attachments/" + attachment["id"] + "/preview", expect=415)
            self.request("foreign account order " + code, "GET", root, token="other", expect=403)
            self.request("foreign account documents " + code, "GET", root + "/attachments", token="other", expect=403)
            self.request("foreign account delivery " + code, "GET", root + "/deliveries", token="other", expect=403)
            self.request("results before payment " + code, "GET", root + "/deliveries", expect=409)
            self.request("message write " + code, "POST", root + "/messages", body={"body": "Mensaje sintetico de seguimiento"}, expect=201)
            self.request("message read " + code, "GET", root + "/messages")
            self.request("message isolation " + code, "GET", root + "/messages", token="other", expect=403)
            self.request("message scope " + code, "POST", root + "/messages", body={"body": "Denied"}, token="messages:read", expect=403)
            self.request("invoice scope " + code, "GET", "/api/v1/invoices", token="orders:read", expect=403)
            invoice = self.control("invoice", order_id=order, tax_basis_points=1800, price_by_option={"pre_employment": 125050, "routine": 180075} if code == "polygraph" else {})
            self.check("admin quote " + code, invoice.get("ok"), evidence=invoice.get("status"))
            result = self.control("result", order_id=order, file=True)
            self.check("admin prepares synthetic result " + code, result.get("ok"))
            if not result.get("ok") or not invoice.get("ok"):
                continue
            rid = result["result_id"]
            iid = invoice["invoice_id"]
            self.request("unpaid result detail " + code, "GET", root + f"/results/{rid}", expect=409)
            self.request("unpaid result download " + code, "GET", root + f"/results/{rid}/download", expect=409)
            self.check("admin completes " + code, self.control("complete", order_id=order).get("ok"))
            self.control("payment", order_id=order, amount=invoice["total"] - 1)
            self.request("partial payment blocks " + code, "GET", root + "/deliveries", expect=409)
            _, detail, _, _ = self.request("invoice detail " + code, "GET", f"/api/v1/invoices/{iid}")
            if code == "polygraph":
                lines = detail.get("data", {}).get("lines", [])
                self.check("modalities split the invoice by actual person counts and confirmed rates",
                           [(line.get("option_code"), line["quantity"], line["unit_price"], line["total"]) for line in lines]
                           == [("pre_employment", 2, 125050, 250100), ("routine", 1, 180075, 180075)])
                self.check("mixed modalities reconcile to invoice totals", invoice["total"] == 507607)
            self.request("invoice printable " + code, "GET", f"/api/v1/invoices/{iid}/document")
            self.request("invoice isolation " + code, "GET", f"/api/v1/invoices/{iid}", token="other", expect=403)
            self.control("payment", order_id=order, amount=1)
            _, pending, _, _ = self.request("paid unpublished " + code, "GET", root + "/deliveries")
            self.check("no unpublished items " + code, pending.get("data") == [])
            self.check("admin publishes " + code, self.control("publish", order_id=order, result_id=rid).get("ok"))
            self.request("published manifest " + code, "GET", root + "/deliveries")
            if index == 0:
                self.request("results manifest alias", "GET", root + "/results")
                self.request("overdue invoice filter", "GET", "/api/v1/invoices?status=overdue", token="invoices:read")
            _, published, _, _ = self.request("published metadata " + code, "GET", root + f"/results/{rid}")
            if code == "remote-interview-rppg":
                findings = published.get("data", {}).get("verification_findings") or {}
                self.check("interview publishes human review per participant after payment", findings.get("method") == "manual"
                           and [(check["service_code"], check["outcome"]) for check in findings.get("checks", [])]
                           == [(code, "reviewed"), (code, "reviewed")])
            _, _, binary, headers = self.request("published download " + code, "GET", root + f"/results/{rid}/download")
            self.check("delivery checksum " + code, hashlib.sha256(binary).hexdigest() == headers.get("X-Content-SHA256"))
            self.request("published preview PDF blocked " + code, "GET", root + f"/results/{rid}/preview", expect=415)
            self.request("foreign download " + code, "GET", root + f"/results/{rid}/download", token="other", expect=403)
            self.upload(order, person, "supporting", pdf("CLOSED ORDER"), expect=409)
            self.request("closed message rejected " + code, "POST", root + "/messages", body={"body": "After completion"}, expect=(201, 409))

    def files_and_batches(self):
        self.control("clear-cache")
        codes = ["university-degree", "exequatur", "identity-verification"]
        people = [profile(codes, "00000000000"), profile(codes, "00000000001")]
        data = order_payload(codes, people)
        created, _ = self.create(codes, payload=data)
        oid = created["id"]
        pid = created["people"][0]["id"]
        self.upload(oid, None, "identity_front", pdf("NO PERSON"), expect=422)
        self.check("cannot quote without evidence", self.control("invoice", order_id=oid).get("status") == 422)
        doc = pdf("FRONT UNIQUE")
        key = "doc-repeat-001"
        first = self.upload(oid, pid, "identity_front", doc, key=key)[1].get("data", {})
        repeat = self.upload(oid, pid, "identity_front", doc, key=key)[1].get("data", {})
        self.check("document idempotency", first.get("id") == repeat.get("id"))
        self.upload(oid, pid, "identity_back", doc, "renamed.pdf", expect=422)
        self.upload(oid, pid, "identity_front", pdf("CHANGED"), key=key, expect=409)
        other, _ = self.create(["identity-verification"])
        self.upload(oid, other["people"][0]["id"], "selfie", png(44), "person.png", "image/png", expect=404)
        for filename, mime, content in [("audio.mp3", "audio/mpeg", b"ID3audio"), ("vector.svg", "image/svg+xml", b'<svg xmlns="http://www.w3.org/2000/svg"/>'),
                                      ("fake.pdf", "application/pdf", b"not a PDF"), ("macro.docx", "application/zip", docx(True)),
                                      ("archive.zip", "application/zip", docx()), ("legacy.doc", "application/msword", b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1" + b"0" * 512)]:
            self.upload(oid, pid, "supporting", content, filename, mime, expect=422)
        self.upload(oid, pid, "selfie", pdf("NOT A SELFIE"), expect=422)
        self.upload(oid, pid, "degree_document", docx(), "title.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", expect=422)
        self.upload(oid, pid, "supporting", png(42, 10001, 1), "wide.png", "image/png", expect=422)
        self.upload(oid, pid, "supporting", pdf("TOO BIG") + b" " * (10 * 1024 * 1024), "large.pdf", expect=422)
        image = self.upload(oid, pid, "selfie", png(75), "selfie.png", "image/png")[1]["data"]
        _, _, image_bytes, headers = self.request("private raster preview", "GET", f'/api/v1/orders/{oid}/attachments/{image["id"]}/preview')
        self.check("preview integrity and no-store", image_bytes == png(75) and "no-store" in headers.get("Cache-Control", ""))
        for person_index, person in enumerate(created["people"]):
            for purpose in ["identity_front", "degree_document", "exequatur_document", "selfie"]:
                if person_index == 0 and purpose in ["identity_front", "selfie"]:
                    continue
                selfie = purpose == "selfie"
                self.upload(oid, person["id"], purpose, png(90 + person_index) if selfie else pdf(f"BATCH {person_index} {purpose}"),
                            "selfie.png" if selfie else purpose + ".pdf", "image/png" if selfie else "application/pdf")
        _, detail, _, _ = self.request("batch evidence complete", "GET", f"/api/v1/orders/{oid}")
        self.check("every person has independent evidence", detail["data"]["verification"]["ready_for_review"] is True)
        for mutation in ["duplicate-id", "bad-quantity", "11-people", "missing-title", "future-date", "unnecessary-id", "forged-decision"]:
            bad = order_payload(["university-degree", "exequatur"], [profile(["university-degree", "exequatur"], "00000000000"), profile(["university-degree", "exequatur"], "00000000001")])
            if mutation == "duplicate-id": bad["people"][1]["identity_number"] = "000-0000000-0"
            if mutation == "bad-quantity": bad["items"][0]["quantity"] = 99
            if mutation == "11-people": bad["people"] *= 6
            if mutation == "missing-title": del bad["people"][0]["degree"]
            if mutation == "future-date": bad["people"][0]["exequatur"]["issued_on"] = "2099-01-01"
            if mutation == "unnecessary-id": bad = order_payload(["psychometric"]); bad["people"][0]["identity_number"] = "00000000000"
            if mutation == "forged-decision": bad["people"][0]["decision"] = "verified"
            self.create([], payload=bad, expect=422, name="invalid batch " + mutation)

    def identity_flows(self):
        self.control("clear-cache")
        self.control("mode", mode="didit")
        consent = {"consent": True, "notice_version": self.manifest["notice"]}
        reuse = {"consent": True, "notice_version": self.manifest["reuse_notice"]}
        order, _ = self.create(["identity-verification"])
        oid, pid = order["id"], order["people"][0]["id"]
        root = f"/api/v1/orders/{oid}/people/{pid}/identity-session"
        self.request("identity consent required", "POST", root, body={}, expect=422)
        self.request("identity scope required", "POST", root, body=consent, token="orders:write", expect=403)
        for field in ["portrait_image", "workflow_id", "decision"]:
            self.request("identity forbids " + field, "POST", root, body={**consent, field: "forged"}, expect=422)
        self.request("credit identity accepted", "POST", root, body=consent, expect=202)
        before = self.control("snapshot")["provider_creates"]
        self.request("identity retry", "POST", root, body=consent, expect=202)
        self.check("identity retry creates no extra remote session", self.control("snapshot")["provider_creates"] == before)
        self.request("identity capture state", "GET", root)
        self.request("identity pending result blocked", "GET", root + "/result", expect=409)
        self.request("foreign identity result blocked", "GET", root + "/result", token="other", expect=403)
        self.control("provider", order_id=oid, status="Approved")
        envelope = self.control("envelope", order_id=oid)["body"]
        canonical = json.dumps(envelope, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()
        signature = hmac.new(b"synthetic-api-lab-signature-secret", canonical, hashlib.sha256).hexdigest()
        wh = {"Content-Type": "application/json", "X-Timestamp": str(envelope["timestamp"]), "X-Signature-V2": signature}
        self.request("forged callback rejected", "POST", "/api/providers/didit/webhook", raw=canonical, headers={**wh, "X-Signature-V2": "0" * 64}, token=None, expect=401)
        self.request("signed callback", "POST", "/api/providers/didit/webhook", raw=canonical, headers=wh, token=None)
        self.request("callback replay idempotent", "POST", "/api/providers/didit/webhook", raw=canonical, headers=wh, token=None)
        _, result, _, _ = self.request("credit technical result automatic", "GET", root + "/result")
        self.check("active document verification confirmed", result.get("data", {}).get("outcome") == "verified")
        self.request("no ordinary unpaid delivery exception", "GET", f"/api/v1/orders/{oid}/deliveries", expect=409)
        enrolled = self.request("enroll approved identity", "POST", f"/api/v1/orders/{oid}/people/{pid}/reusable-identity", body=reuse, expect=201)[1]["data"]
        self.control("clear-cache")
        identity = enrolled["id"]
        self.request("identity registry", "GET", "/api/v1/identities")
        self.request("registry isolated", "GET", "/api/v1/identities", token="other")
        recurring_path = f"/api/v1/identities/{identity}/verifications"
        self.request("recurring portrait replacement blocked", "POST", recurring_path, body={**reuse, "portrait_image": "forged"}, headers={"Idempotency-Key": "recurring-bad-001"}, expect=422)
        recurrence = self.request("recurring creation", "POST", recurring_path, body=reuse, headers={"Idempotency-Key": "recurring-good-001"}, expect=202)[1]["data"]
        self.request("recurring idempotency", "POST", recurring_path, body=reuse, headers={"Idempotency-Key": "recurring-good-001"}, expect=202)
        self.request("single pending reference", "POST", recurring_path, body=reuse, headers={"Idempotency-Key": "recurring-good-002"}, expect=409)
        roid = recurrence["order_id"]
        self.control("provider", order_id=roid, status="Approved")
        self.control("sync", order_id=roid)
        recurring_result = recurrence["identity_session_url"]
        # Session links use APP_URL; send the path to this lab, never follow an external host.
        recurring_result = "/api/v1/" + recurring_result.split("/api/v1/", 1)[1] + "/result"
        _, final, _, _ = self.request("recurring result", "GET", recurring_result)
        self.check("recurring does not revalidate document", final.get("data", {}).get("outcome") == "verified" and final["data"]["document_reverified"] is False)
        self.control("partner", enabled=False)
        self.request("suspended partner loses result access", "GET", recurring_result, expect=403)
        self.control("partner", enabled=True)
        self.control("provider", order_id=oid, status="Declined")
        self.control("sync", order_id=oid)
        self.request("source correction blocks obsolete result", "GET", recurring_result, expect=409)
        self.control("sync", order_id=roid)
        updated = self.request("dependent conclusion updated", "GET", recurring_result)[1]
        self.check("corrected result inconclusive", updated.get("data", {}).get("outcome") == "inconclusive")
        self.request("reference revoke", "POST", f"/api/v1/identities/{identity}/revoke", body={}, expect=204)
        self.request("revoked reference blocked", "POST", recurring_path, body=reuse, headers={"Idempotency-Key": "recurring-revoked-001"}, expect=409)
        for scenario, status, options, outcome in [("declined", "Declined", {}, "not_verified"), ("review", "In Review", {}, "inconclusive"),
                                                    ("passive", "Approved", {"method": "PASSIVE"}, "inconclusive"), ("mismatch", "Approved", {"mismatch": True}, "inconclusive"),
                                                    ("empty", "Approved", {"empty_checks": True}, "inconclusive")]:
            self.control("clear-cache")
            case, _ = self.create(["identity-verification"])
            path = f'/api/v1/orders/{case["id"]}/people/{case["people"][0]["id"]}/identity-session'
            self.request("start identity " + scenario, "POST", path, body=consent, expect=202)
            self.control("provider", order_id=case["id"], status=status, **options)
            self.control("sync", order_id=case["id"])
            actual = self.request("result identity " + scenario, "GET", path + "/result")[1]
            self.check("identity outcome " + scenario, actual.get("data", {}).get("outcome") == outcome)
        untrusted, _ = self.create(["identity-verification"], token="untrusted")
        self.request("ordinary account cannot start identity API", "POST", f'/api/v1/orders/{untrusted["id"]}/people/{untrusted["people"][0]["id"]}/identity-session', body=consent, token="untrusted", expect=403)

    def identity_resilience(self):
        self.control("mode", mode="didit")
        self.control("clear-cache")
        consent = {"consent": True, "notice_version": self.manifest["notice"]}
        reuse = {"consent": True, "notice_version": self.manifest["reuse_notice"]}
        case, _ = self.create(["identity-verification"])
        oid, pid = case["id"], case["people"][0]["id"]
        root = f"/api/v1/orders/{oid}/people/{pid}"
        before = self.control("snapshot")["provider_creates"]
        self.control("provider-behavior", create_timeout=True)
        self.request("lost provider creation response", "POST", root + "/identity-session", body=consent, expect=202)
        self.request("uncertain creation retry", "POST", root + "/identity-session", body=consent, expect=202)
        self.check("timeout accepted remotely only once", self.control("snapshot")["provider_creates"] == before + 1)
        self.control("provider-behavior")
        envelope = self.control("envelope", order_id=oid)["body"]
        def callback(name, payload, expect=200):
            canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()
            signature = hmac.new(b"synthetic-api-lab-signature-secret", canonical, hashlib.sha256).hexdigest()
            return self.request(name, "POST", "/api/providers/didit/webhook", raw=canonical, token=None, expect=expect,
                                headers={"Content-Type": "application/json", "X-Timestamp": str(int(time.time())), "X-Signature-V2": signature})
        callback("signed wrong application", {**envelope, "application_id": str(uuid.uuid4())}, 404)
        callback("signed wrong workflow", {**envelope, "workflow_id": str(uuid.uuid4())}, 404)
        callback("signed wrong environment", {**envelope, "environment": "live"}, 404)
        callback("stale signed body with fresh header", {**envelope, "timestamp": int(time.time()) - 3600}, 401)
        # A signed body claiming Approved must not replace the provider's Not Started.
        callback("recover timed-out session through signed metadata", envelope)
        self.request("callback cannot invent result", "GET", root + "/identity-session/result", expect=409)
        self.check("recovery made no duplicate POST", self.control("snapshot")["provider_creates"] == before + 1)
        self.control("provider", order_id=oid, status="Approved")
        self.control("sync", order_id=oid)
        identity = self.request("register resilient reference", "POST", root + "/reusable-identity", body=reuse, expect=201)[1]["data"]["id"]
        self.control("provider-behavior", read_failure=True)
        self.request("cannot reuse cached source during provider outage", "POST", f"/api/v1/identities/{identity}/verifications", body=reuse,
                     headers={"Idempotency-Key": "outage-reference-001"}, expect=503)
        self.check("source outage creates no session", self.control("snapshot")["provider_creates"] == before + 1)
        self.control("provider-behavior")
        self.control("expire-reference", identity_id=identity)
        self.request("expired reference needs document", "POST", f"/api/v1/identities/{identity}/verifications", body=reuse,
                     headers={"Idempotency-Key": "expired-reference-001"}, expect=409)
        self.control("clear-cache")
        passive, _ = self.create(["identity-verification"])
        path = f'/api/v1/orders/{passive["id"]}/people/{passive["people"][0]["id"]}/identity-session'
        self.control("provider-behavior", passive_workflow=True)
        self.request("passive workflow creation paused", "POST", path, body=consent, expect=202)
        self.check("passive preflight sent no POST", self.control("snapshot")["provider_creates"] == before + 1)
        state = self.request("passive workflow attention state", "GET", path)[1]["data"]["state"]
        self.check("passive workflow needs attention", state == "attention_required")
        self.control("provider-behavior")
        self.control("partner", enabled=True, limit=1)
        quota, _ = self.create(["identity-verification"])
        self.request("partner daily quota enforced", "POST", f'/api/v1/orders/{quota["id"]}/people/{quota["people"][0]["id"]}/identity-session', body=consent, expect=429)
        self.control("partner", enabled=True, limit=100)

    def concurrency_and_limits(self):
        self.control("clear-cache")
        key = "race-" + uuid.uuid4().hex
        def create(index):
            return self.request("parallel create", "POST", "/api/v1/orders", body=order_payload(["training-equipment"]), headers={"Idempotency-Key": key}, port=self.ports[index % 2], record=False)
        with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
            replies = list(pool.map(create, range(8)))
        self.check("8 concurrent retries return 201", all(r[0] == 201 for r in replies), statuses=[r[0] for r in replies])
        ids = {r[1].get("data", {}).get("id") for r in replies}
        self.check("concurrent retries create one order", len(ids) == 1 and None not in ids)
        self.control("clear-cache")
        replies = [self.request("rate burst", "GET", "/api/v1/services", token="other", record=False) for _ in range(62)]
        self.check("rate limit permits first 60", all(r[0] == 200 for r in replies[:60]))
        self.check("rate limit blocks 61 and 62", [r[0] for r in replies[60:]] == [429, 429])
        self.check("rate limit has retry-after", "Retry-After" in replies[-1][3])
        self.control("clear-cache")
        self.control("mode", mode="didit")
        order, _ = self.create(["identity-verification"])
        upload_path = f'/api/v1/orders/{order["id"]}/attachments'
        for index in range(10):
            self.request("upload quota attempt " + str(index + 1), "POST", upload_path, fields={}, expect=422)
        self.request("upload quota blocks 11", "POST", upload_path, fields={}, expect=429)
        self.request("upload quota does not block identity", "POST", f'/api/v1/orders/{order["id"]}/people/{order["people"][0]["id"]}/identity-session',
                     body={"consent": True, "notice_version": self.manifest["notice"]}, expect=202)
        self.control("clear-cache")

    def scenario_groups(self):
        return [("authentication-contract", self.auth_and_contract), ("all-services", self.catalog_and_services),
                ("files-batches", self.files_and_batches), ("identity", self.identity_flows), ("identity-resilience", self.identity_resilience), ("concurrency-limits", self.concurrency_and_limits)]

    def run(self, only=None):
        try:
            self.start()
            for title, method in self.scenario_groups():
                if only and title not in only:
                    continue
                print("RUN: " + title, flush=True)
                try:
                    method()
                except Exception as error:
                    self.check(title + " completed", False, error=type(error).__name__)
                    print("Scenario stopped: " + title + " / " + type(error).__name__, flush=True)
                self.control("clear-cache")
            snapshot = self.control("snapshot")
            self.check("no orphaned private files after rejected requests", snapshot["files"] == snapshot["stored_file_rows"])
            self.check("database foreign keys intact", snapshot["foreign_key_violations"] == 0)
            webhook_text = json.dumps(snapshot.get("webhooks", []), ensure_ascii=False)
            self.check("webhooks have no conclusions or capture links", all(value not in webhook_text for value in ["RESERVADO", "00000000000", "verification_url", "verify.didit.me", "identity_number"]))
        finally:
            for process in self.processes:
                process.terminate()
                try:
                    process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait()
            for log in self.logs:
                log.close()
        spec = json.loads((BASE / "packages/contracts/openapi.json").read_text(encoding="utf-8"))
        coverage = []
        for path, operations in spec["paths"].items():
            for method in operations:
                if method not in {"get", "post", "put", "patch", "delete"}:
                    continue
                pattern = "^/api/v1" + re.sub(r"\{[^}]+\}", "[^/]+", path) + "$"
                coverage.append({"method": method.upper(), "path": path, "exercised": any(r.get("method") == method.upper() and re.match(pattern, r.get("path", "")) for r in self.results)})
        if not only:
            self.check("all OpenAPI operations exercised", all(operation["exercised"] for operation in coverage), missing=[x for x in coverage if not x["exercised"]])
        report = {"provider": "HTTP fake inside Laravel; actual client-to-ATD loopback HTTP", "database": "isolated SQLite WAL", "http_requests": self.http_count, "operations": coverage, "checks": len(self.results),
                  "passed": sum(r["passed"] for r in self.results), "failed": sum(not r["passed"] for r in self.results), "results": self.results}
        (self.root / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps({"http_requests": self.http_count, "checks": report["checks"], "passed": report["passed"], "failed": report["failed"], "report": str(self.root / "report.json")}), flush=True)
        return report["failed"] == 0


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--only", help="Comma-separated scenario groups, for focused regression")
    options = parser.parse_args()
    selected = options.only.split(",") if options.only else None
    if selected and set(selected) - {"authentication-contract", "all-services", "files-batches", "identity", "identity-resilience", "concurrency-limits"}:
        parser.error("Unknown scenario group")
    raise SystemExit(0 if LabRun().run(selected) else 1)
