"""Controlled Didit Sandbox or opt-in Live pilot through ATD's HTTP API; no public server."""
import argparse
import getpass
import json
from pathlib import Path
import re
import sys
import uuid
import warnings

from run import BASE, LabRun, order_payload

POINTER = BASE / "storage/app/private/api-lab/active-pilot.json"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["prepare", "new", "check", "status", "recurrent", "diagnose"])
    parser.add_argument("--live", action="store_true", help="Opt-in local Live pilot; preparation asks for private data and consent")
    parser.add_argument("--scenario", choices=["approve", "decline_document_expired", "decline_face_match_low_similarity", "review_face_match_borderline"], default=None)
    args = parser.parse_args()
    existing = json.loads(POINTER.read_text(encoding="utf-8")) if POINTER.exists() else None
    if args.live and args.scenario:
        raise SystemExit("Live no acepta escenarios simulados.")
    if args.live and existing and existing.get("environment", "sandbox") != "live":
        old_root = Path(existing["root"]).resolve()
        if old_root.parent != (BASE / "storage/app/private/api-lab").resolve():
            raise SystemExit("Ruta previa de piloto no valida.")
        (old_root / "pilot-state.json").write_text(json.dumps(existing), encoding="utf-8")
        existing = None
    if args.action == "new":
        if existing and existing.get("environment") == "live":
            if existing.get("order_id"):
                raise SystemExit("Ya hay una campaña Live. Use status/recurrent para no crear otro cargo inicial.")
            args.action = "prepare"
        else:
            if existing:
                old_root = Path(existing["root"]).resolve()
                if old_root.parent != (BASE / "storage/app/private/api-lab").resolve():
                    raise SystemExit("Ruta previa de piloto no valida.")
                (old_root / "pilot-state.json").write_text(json.dumps(existing), encoding="utf-8")
            existing = None
            args.action = "prepare"
    if not existing and args.action not in ["prepare", "check"]:
        raise SystemExit("Primero prepare una captura: python tests/Support/api-lab/pilot.py prepare")
    realm = existing.get("environment", "sandbox") if existing else ("live" if args.live else "sandbox")
    scenario = existing.get("scenario", "approve") if existing else (args.scenario or "approve")
    if existing and args.scenario and args.scenario != scenario:
        raise SystemExit("No cambie el escenario de una sesión existente; prepare otro piloto explícito.")
    live_profile = None
    if realm == "live" and args.action == "prepare" and not (existing and existing.get("order_id")):
        if not sys.stdin.isatty():
            raise SystemExit("Live requiere una terminal interactiva para consentimiento y datos privados. No pase datos por argumentos ni chat.")
        print("PILOTO LIVE: una verificacion inicial y una recurrente. Tarifas vigentes de Didit; limite de dos sesiones en esta base.")
        print("Didit recibira su documento, rostro y prueba de vida; ATD guardara datos cifrados y consentimiento de reutilizacion hasta 90 dias.")
        print("Los 90 dias son vigencia operativa ATD, no borrado automatico de medios en Didit.")
        print("Confirme primero que cambio la aplicacion Didit de prueba a Live. No hay selector de resultados simulados.")
        if input("Escriba AUTORIZO LIVE para continuar: ").strip() != "AUTORIZO LIVE":
            raise SystemExit("Cancelado sin crear sesiones.")
        warnings.simplefilter("error", getpass.GetPassWarning)
        first = getpass.getpass("Nombre real (oculto): ").strip()
        last = getpass.getpass("Apellidos reales (ocultos): ").strip()
        number = getpass.getpass("Cedula dominicana (oculta): ").strip()
        address = getpass.getpass("Direccion autorizada (oculta): ").strip()
        if not first or not last or len(first) > 100 or len(last) > 100 or not re.fullmatch(r"[0-9]{3}-?[0-9]{7}-?[0-9]", number) or not address or len(address) > 1000:
            raise SystemExit("Datos invalidos. No se creo ninguna sesion.")
        live_profile = {"first_name": first, "last_name": last, "identity_number": number, "address": address,
                        "purpose": "Prueba de integracion Live autorizada por el titular", "authorization_confirmed": True, "service_codes": ["identity-verification"]}
    lab = LabRun(provider="didit", root=existing["root"] if existing else None)
    lab.env.update(DIDIT_ENVIRONMENT=realm, DIDIT_SANDBOX_SCENARIO=scenario)
    if realm == "live":
        lab.env["ATD_API_LAB_LIVE_CONFIRMED"] = "1"
    if lab.root.parent != (BASE / "storage/app/private/api-lab").resolve():
        raise SystemExit("Ruta de piloto no valida.")
    try:
        lab.start(resume=bool(existing))
        if existing:
            if not lab.control("resume-pilot-access").get("ok"):
                raise RuntimeError("El acceso del piloto fue revocado o suspendido; no se renueva automaticamente.")
            lab.manifest = json.loads((lab.root / "manifest.json").read_text(encoding="utf-8"))
            lab.tokens = lab.manifest["tokens"]
        ready = lab.control("mode", mode="didit")
        if not ready.get("ok"):
            raise RuntimeError("La configuracion no corresponde al entorno local confirmado del piloto.")
        state = existing or {"root": str(lab.root), "order_id": None, "person_id": None, "recurring": None, "environment": realm, "scenario": scenario}
        # Persist before the first POST so an interrupted command never creates another order blindly.
        if not existing:
            state["create_key"] = "pilot-initial-" + uuid.uuid4().hex
            POINTER.write_text(json.dumps(state), encoding="utf-8")
        if args.action == "check":
            result = lab.control("preflight")
            print(json.dumps({"preflight": result.get("ok"), "configured_environment": realm, "provider_environment_confirmed": False,
                              "maximum_sessions": 2 if realm == "live" else None}))
            return 0 if result.get("ok") else 1
        if not state["order_id"]:
            if realm == "live" and live_profile is None:
                raise RuntimeError("Primero ejecute prepare --live y confirme sus datos en la terminal privada.")
            if not lab.control("preflight").get("ok"):
                raise RuntimeError("Preflight no confirmado. No se creo ninguna sesion Didit.")
            payload = order_payload(["identity-verification"], people=[live_profile]) if live_profile else order_payload(["identity-verification"])
            created, _ = lab.create(["identity-verification"], payload=payload, key=state["create_key"])
            if not created:
                raise RuntimeError("No se pudo crear la solicitud aislada; no se envio captura.")
            state.update(order_id=created["id"], person_id=created["people"][0]["id"])
            POINTER.write_text(json.dumps(state), encoding="utf-8")
        root = f'/api/v1/orders/{state["order_id"]}/people/{state["person_id"]}'
        if args.action == "diagnose":
            diagnostics = lab.control("provider-diagnostics", order_id=state["order_id"])
            print(json.dumps(diagnostics))
            return 0 if diagnostics.get("ok") else 1
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
        if realm == "live":
            diagnostics = lab.control("provider-diagnostics", order_id=state["recurring"]["order_id"] if state["recurring"] and args.action in ["status", "recurrent"] else state["order_id"])
            if not diagnostics.get("ok") or diagnostics.get("environment") != "live":
                raise RuntimeError("Didit no confirmo entorno Live. No se entrega el enlace; no repita con otra clave.")
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
    except (EOFError, KeyboardInterrupt, getpass.GetPassWarning):
        print("Entrada privada cancelada o terminal no compatible. No se crea ninguna sesion sin consentimiento y datos validos.", file=sys.stderr)
        raise SystemExit(1)
    except (RuntimeError, ValueError, KeyError, OSError) as error:
        print("Piloto detenido de forma segura: " + str(error), file=sys.stderr)
        raise SystemExit(1)
