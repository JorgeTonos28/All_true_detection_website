"""Adversarial external-client HTTP tests for ATD identity. Didit is fault-injected."""
import concurrent.futures
import hashlib
import hmac
import json
import uuid

from run import LabRun, order_payload


class RedTeam(LabRun):
    def scenario_groups(self):
        return [("identity-redteam", self.attacks)]

    def callback(self, order_id, *, body_changes=None, expect=200):
        envelope = self.control("envelope", order_id=order_id)["body"]
        envelope.update(body_changes or {})
        canonical = json.dumps(envelope, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()
        signature = hmac.new(b"synthetic-api-lab-signature-secret", canonical, hashlib.sha256).hexdigest()
        self.request("adversarial signed callback", "POST", "/api/providers/didit/webhook", raw=canonical, token=None, expect=expect,
                     headers={"Content-Type": "application/json", "X-Timestamp": str(envelope["timestamp"]), "X-Signature-V2": signature})

    def attacks(self):
        self.control("mode", mode="didit")
        consent = {"consent": True, "notice_version": self.manifest["notice"]}
        reuse = {"consent": True, "notice_version": self.manifest["reuse_notice"]}
        case, _ = self.create(["identity-verification"])
        oid, pid = case["id"], case["people"][0]["id"]
        source = f"/api/v1/orders/{oid}/people/{pid}"
        path = source + "/identity-session"
        before = self.control("snapshot")
        for token in ["other", "untrusted", "inactive", "unverified", "orders:write"]:
            self.request("cannot start foreign or unprivileged identity " + token, "POST", path, body=consent, token=token, expect=403)
        for bad in [{"consent": False, "notice_version": self.manifest["notice"]}, {"consent": True, "notice_version": "obsolete"}]:
            self.request("consent bypass blocked", "POST", path, body=bad, expect=422)
        after = self.control("snapshot")
        self.check("denied attempts have no provider side effects", before["sessions"] == after["sessions"] and before["provider_creates"] == after["provider_creates"])
        self.control("clear-cache")
        for field in ["portrait_image", "reference_image", "workflow_id", "decision"]:
            self.request("forbidden provider override " + field, "POST", path, body={**consent, field: "attacker-value"}, expect=422)
        self.request("create with write-only identity scope", "POST", path, body=consent, token="identity:verify", expect=202)
        reply = self.request("POST replay cannot bypass read scope", "POST", path, body=consent, token="identity:verify", expect=202)[1]
        self.check("no capture capability through write-only POST", reply["data"]["verification_url"] is None)
        self.request("write-only GET forbidden", "GET", path, token="identity:verify", expect=403)
        self.request("write-only result forbidden", "GET", path + "/result", token="identity:verify", expect=403)
        self.request("authorized capture lookup", "GET", path, token="identity:read")
        snapshot = self.control("snapshot")
        self.check("one remote creation despite POST retries", snapshot["provider_creates"] == before["provider_creates"] + 1)
        for number in ["PREFIX00000000000", "00000000000SUFFIX", "+00000000000", "000.0000000.0"]:
            self.control("provider", order_id=oid, status="Approved", number=number)
            self.control("sync", order_id=oid)
            result = self.request("malformed provider document cannot match", "GET", path + "/result")[1]
            self.check("strict document outcome", result["data"]["outcome"] == "inconclusive")
        self.control("provider", order_id=oid, status="Approved", number="000-0000000-0")
        self.control("sync", order_id=oid)
        result = self.request("valid document with hyphens", "GET", path + "/result")[1]
        self.check("valid document normalization retained", result["data"]["outcome"] == "verified")
        reference = self.request("enroll reference", "POST", source + "/reusable-identity", body=reuse, expect=201)[1]["data"]["id"]
        endpoint = f"/api/v1/identities/{reference}/verifications"
        self.control("clear-cache")
        for field in ["portrait_image", "reference_image", "workflow_id", "decision", "identity_number"]:
            self.request("recurring override blocked " + field, "POST", endpoint, body={**reuse, field: "attacker-value"},
                         headers={"Idempotency-Key": "override-" + uuid.uuid4().hex}, expect=422)
        for operation in ["verifications", "revoke"]:
            self.request("foreign reference " + operation, "POST", f"/api/v1/identities/{reference}/{operation}", body=reuse,
                         token="other", headers={"Idempotency-Key": "foreign-" + uuid.uuid4().hex}, expect=403)
        self.control("clear-cache")
        creates_before = self.control("snapshot")["provider_creates"]
        key = "concurrent-recurrence-" + uuid.uuid4().hex
        def repeat(index):
            return self.request("concurrent recurring retry", "POST", endpoint, body=reuse, headers={"Idempotency-Key": key}, port=self.ports[index % 2], expect=202, record=False)
        with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
            responses = list(pool.map(repeat, range(6)))
        self.check("all concurrent identity retries accepted", all(r[0] == 202 for r in responses), statuses=[r[0] for r in responses])
        orders = {r[1].get("data", {}).get("order_id") for r in responses}
        self.check("one recurring order for concurrent retries", len(orders) == 1 and None not in orders)
        self.check("one recurring provider POST", self.control("snapshot")["provider_creates"] == creates_before + 1)
        recurring = next(r[1]["data"] for r in responses if r[0] == 202)
        roid = recurring["order_id"]
        self.control("provider", order_id=roid, status="Approved")
        self.control("sync", order_id=roid)
        result_path = "/api/v1/" + recurring["identity_session_url"].split("/api/v1/", 1)[1] + "/result"
        result = self.request("recurring technical result", "GET", result_path)[1]
        self.check("recurring no new document", result["data"]["outcome"] == "verified" and result["data"]["document_reverified"] is False)
        requests = self.control("snapshot")["provider_requests"]
        recent = [r for r in requests if r["workflow_id"].startswith("33333333")]
        self.check("recurring forwards no portrait or ID details", len(recent) == 1 and not recent[0]["has_portrait_image"] and not recent[0]["has_expected_details"])
        initial_vendor = next(r["vendor_data"] for r in requests if r["workflow_id"].startswith("22222222"))
        self.check("provider binding remains source-owned", recent[0]["vendor_data"] == initial_vendor and recent[0]["metadata"]["atd_session_id"] != initial_vendor)
        self.control("clear-cache")
        def competing(index):
            return self.request("competing recurring request", "POST", endpoint, body=reuse, headers={"Idempotency-Key": "distinct-" + uuid.uuid4().hex}, port=self.ports[index], record=False)
        before = self.control("snapshot")
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            replies = list(pool.map(competing, range(2)))
        self.check("different keys cannot start two pending captures", sorted(r[0] for r in replies) == [202, 409], statuses=[r[0] for r in replies])
        after = self.control("snapshot")
        self.check("losing concurrent request rolls back order", after["orders"] == before["orders"] + 1 and after["provider_creates"] == before["provider_creates"] + 1)
        self.control("provider-behavior", read_failure=True)
        self.callback(oid, body_changes={"status": "Declined"})
        self.request("source approval held during unconfirmed update", "GET", path + "/result", expect=409)
        self.request("dependent approval held during unconfirmed update", "GET", result_path, expect=409)
        self.control("provider-behavior")
        self.control("provider", order_id=oid, status="Declined")
        self.control("sync", order_id=oid)
        self.control("sync", order_id=roid)
        result = self.request("dependent result corrected", "GET", result_path)[1]
        self.check("no stale verified outcome after correction", result["data"]["outcome"] == "inconclusive")
        self.control("partner", enabled=False)
        self.request("suspended partner cannot read identity", "GET", path, expect=403)
        self.request("suspended partner cannot read result", "GET", result_path, expect=403)
        self.request("suspended partner cannot enumerate references", "GET", "/api/v1/identities", expect=403)


if __name__ == "__main__":
    raise SystemExit(0 if RedTeam().run(["identity-redteam"]) else 1)
