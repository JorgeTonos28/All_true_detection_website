"""Safety regressions for replacing a misdirected Live pilot. No network or PII."""
import unittest

from pilot import can_replace_misdirected_live, confirm_live_binding


class PilotRecoveryTests(unittest.TestCase):
    def setUp(self):
        self.binding = {"binding_matches": True, "expected_environment": "live", "environment": "sandbox",
                        "status": "Not Started", "application_id": "sandbox-app"}
        self.profile = {"ok": True, "application_id": "live-app"}

    def test_only_confirmed_unstarted_sandbox_can_be_replaced_with_a_different_application(self):
        self.assertTrue(can_replace_misdirected_live(self.binding, self.profile))
        for field, value in [("binding_matches", False), ("expected_environment", "sandbox"), ("environment", "live"),
                             ("environment", None), ("status", "In Progress"), ("status", "Approved")]:
            with self.subTest(field=field, value=value):
                self.assertFalse(can_replace_misdirected_live({**self.binding, field: value}, self.profile))
        self.assertFalse(can_replace_misdirected_live(self.binding, {"ok": True, "application_id": "sandbox-app"}))
        self.assertFalse(can_replace_misdirected_live(self.binding, {"ok": False, "application_id": "live-app"}))

    def test_binding_probe_is_read_only_and_explains_the_confirmed_environment(self):
        class FakeLab:
            def __init__(self, response):
                self.response = response
                self.calls = []

            def control(self, action, **data):
                self.calls.append((action, data))
                return self.response

        lab = FakeLab({"ok": False, **self.binding})
        with self.assertRaisesRegex(RuntimeError, "sandbox; se esperaba live"):
            confirm_live_binding(lab, "synthetic-order")
        self.assertEqual([("provider-binding", {"order_id": "synthetic-order"})], lab.calls)
        good = FakeLab({"ok": True, **self.binding, "environment": "live"})
        self.assertEqual("live", confirm_live_binding(good, "synthetic-order")["environment"])

    def test_missing_create_receipt_is_not_reported_as_an_environment_mismatch(self):
        class FakeLab:
            def control(self, action, **data):
                return {"ok": False, "reason": "creation_not_confirmed", "local_state": "create_uncertain", "failure_code": "not_recorded"}

        with self.assertRaisesRegex(RuntimeError, "creacion no esta confirmada: create_uncertain"):
            confirm_live_binding(FakeLab(), "synthetic-order")


if __name__ == "__main__":
    unittest.main()
