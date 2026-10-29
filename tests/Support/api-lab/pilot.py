"""One controlled Didit Sandbox capture through ATD's real HTTP API; no public server."""
import argparse
import json
from pathlib import Path
import sys
import uuid

from run import BASE, LabRun, order_payload

POINTER = BASE / "storage/app/private/api-lab/active-pilot.json"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["prepare", "status", "recurrent"])
    args = parser.parse_args()
    existing = json.loads(POINTER.read_text(encoding="utf-8")) if POINTER.exists() else None
    if not existing and args.action != "prepare":
        raise SystemExit("Primero prepare una captura: python tests/Support/api-lab/pilot.py prepare")
    lab = LabRun(provider="didit", root=existing["root"] if existing else None)
    if lab.root.parent != (BASE / "storage/app/private/api-lab").resolve():
        raise SystemExit("Ruta de piloto no valida.")
    try:
        lab.start(resume=bool(existing))
        ready = lab.control("mode", mode="didit")
        if not ready.get("ok"):
            raise RuntimeError("La configuracion no corresponde a un Sandbox local listo.")
        state = existing or {"root": str(lab.root), "order_id": None, "person_id": None, "recurring": None}
        # Persist before the first POST so an interrupted command never creates another order blindly.
        if not existing:
            state["create_key"] = "pilot-initial-" + uuid.uuid4().hex
            POINTER.write_text(json.dumps(state), encoding="utf-8")
        if not state["order_id"]:
            created, _ = lab.create(["identity-verification"], payload=order_payload(["identity-verification"]), key=state["create_key"])
            if not created:
                raise RuntimeError("No se pudo crear la solicitud aislada; no se envio captura.")
            state.update(order_id=created["id"], person_id=created["people"][0]["id"])
            POINTER.write_text(json.dumps(state), encoding="utf-8")
        root = f'/api/v1/orders/{state["order_id"]}/people/{state["person_id"]}'
        consent = {"consent": True, "notice_version": lab.manifest["notice"]}
        reuse = {"consent": True, "notice_version": lab.manifest["reuse_notice"]}
        if args.action == "prepare":
            status, data, _, _ = lab.request("prepare pilot", "POST", root + "/identity-session", body=consent, expect=202, record=False)
            if status != 202:
                raise RuntimeError("No se acepto el piloto; revise configuracion, consentimiento y cuenta de pruebas.")
        else:
            lab.control("sync", order_id=state["order_id"])
        if args.action == "recurrent" and not state["recurring"]:
            status, result, _, _ = lab.request("initial conclusion", "GET", root + "/identity-session/result", record=False)
            if status != 200 or result.get("data", {}).get("outcome") != "verified":
                print("No se crea la reverificacion: la fuente inicial todavia no esta verificada.")
                print("El mock documental de Sandbox puede no coincidir con la cedula DOM sintetica; ATD no omite esa comprobacion.")
                return 1
            status, enrolled, _, _ = lab.request("register pilot reference", "POST", root + "/reusable-identity", body=reuse, record=False)
            if status != 201:
                raise RuntimeError("No se pudo registrar la referencia consentida.")
            state["recurring_key"] = state.get("recurring_key") or "pilot-recurring-" + uuid.uuid4().hex
            state["identity_id"] = enrolled["data"]["id"]
            POINTER.write_text(json.dumps(state), encoding="utf-8")
            status, created, _, _ = lab.request("recurrent pilot", "POST", f'/api/v1/identities/{state["identity_id"]}/verifications', body=reuse,
                                               headers={"Idempotency-Key": state["recurring_key"]}, record=False)
            if status != 202:
                raise RuntimeError("La nueva captura no fue aceptada; no reintente con otra clave.")
            state["recurring"] = created["data"]
            POINTER.write_text(json.dumps(state), encoding="utf-8")
        if state["recurring"] and args.action in ["status", "recurrent"]:
            lab.control("sync", order_id=state["recurring"]["order_id"])
            root = f'/api/v1/orders/{state["recurring"]["order_id"]}/people/{state["recurring"]["person_id"]}'
        status, data, _, _ = lab.request("pilot state", "GET", root + "/identity-session", record=False)
        if status != 200:
            raise RuntimeError("No se pudo consultar la sesion del piloto.")
        session = data["data"]
        print(json.dumps({"state": session["state"], "environment": session["environment"], "verification_kind": session["verification_kind"]}))
        if session["verification_url"]:
            (lab.root / "capture-link.txt").write_text(session["verification_url"], encoding="utf-8")
            print("ENLACE PRIVADO DE CAPTURA (no guardar en Git ni reportes):")
            print(session["verification_url"])
        status, data, _, _ = lab.request("pilot conclusion", "GET", root + "/identity-session/result", record=False)
        if status == 200:
            result = data["data"]
            print(json.dumps({"outcome": result["outcome"], "reason": result["reason"], "revision": result["revision"], "document_reverified": result["document_reverified"]}))
        elif not session["verification_url"]:
            print("Sin enlace confirmado. No se repetira una creacion incierta; revisar en consola por la referencia ATD.")
        return 0 if session["verification_url"] or status == 200 else 1
    finally:
        for process in lab.processes:
            process.terminate()
            process.wait(timeout=10)
        for log in lab.logs:
            log.close()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (RuntimeError, ValueError, KeyError, OSError) as error:
        print("Piloto detenido de forma segura: " + str(error), file=sys.stderr)
        raise SystemExit(1)
