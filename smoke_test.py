#!/usr/bin/env python3
"""
Smoke test for the TAQA Workforce Operations Management System.
Assumes the Laravel backend is running on http://127.0.0.1:8000.
Creates temporary records, exercises the API, then cleans up.
"""

import json
import sys
import urllib.request
import urllib.error
from datetime import date

BASE = "http://127.0.0.1:8000"


def request(method, path, data=None):
    url = BASE + path
    body = json.dumps(data).encode() if data is not None else None
    req = urllib.request.Request(url, data=body, method=method,
                                 headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            return resp.status, json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        try:
            return e.code, json.loads(raw)
        except json.JSONDecodeError:
            return e.code, {"raw": raw[:200]}


def expect_ok(status, body, label):
    if status < 200 or status >= 300:
        print(f"FAIL {label}: HTTP {status} -> {body}")
        return False
    print(f"PASS {label}")
    return True


def bootstrap():
    status, body = request("GET", "/api/bootstrap")
    return body if status == 200 else None


class TestRunner:
    def __init__(self):
        self.ids = {}
        self.passed = 0
        self.failed = 0

    def check(self, ok, label):
        if ok:
            self.passed += 1
        else:
            self.failed += 1
            print(f"  ^^ {label}")

    def create_setting(self, table, payload):
        status, body = request("POST", f"/api/settings/{table}", payload)
        ok = expect_ok(status, body, f"create {table}")
        self.check(ok, f"create {table}")
        return body.get("id") if ok else None

    def setup(self):
        print("--- setup reference data ---")
        self.ids["client"] = self.create_setting("clients", {"name": "Smoke Client"})
        self.ids["location"] = self.create_setting("locations", {"name": "Smoke Site"})
        self.ids["department"] = self.create_setting("departments", {"name": "Smoke Dept"})
        self.ids["position"] = self.create_setting("positions", {
            "name": "Smoke Position",
            "department_id": self.ids["department"]
        })
        self.ids["field"] = self.create_setting("fields", {
            "name": "Smoke Field",
            "client_id": self.ids["client"],
            "location_id": self.ids["location"]
        })
        self.ids["project"] = self.create_setting("projects", {
            "name": "Smoke Project",
            "client_id": self.ids["client"],
            "field_id": self.ids["field"]
        })
        self.ids["doc_type"] = self.create_setting("document_types", {"name": "Smoke Doc"})
        self.ids["cert_type"] = self.create_setting("certificate_types", {"name": "Smoke Cert"})
        self.ids["equipment_type"] = self.create_setting("equipment_types", {"name": "Smoke Gear"})
        self.ids["leave_type"] = self.create_setting("leave_types", {"name": "Smoke Leave"})

        # shift / rotation are not in the settings menu but referenced by deployments
        self.ids["shift_type"] = self.create_setting("shift_types", {"name": "Day"})
        self.ids["rotation_type"] = self.create_setting("rotation_types", {"name": "28/28"})

    def test_employee_auto_code(self):
        print("\n--- employee auto code ---")
        status, body = request("POST", "/api/employees", {
            "first_name": "Smoke",
            "last_name": "Tester",
            "contract_type": "TAQA",
            "department_id": self.ids["department"],
            "position_id": self.ids["position"],
            "phone_primary": "000",
            "email": "smoke@test.com",
            "daily_rate": 500,
            "hire_date": str(date.today())
        })
        ok = expect_ok(status, body, "create employee without code")
        self.check(ok, "create employee without code")
        if not ok:
            return None
        code = body.get("employee_code", "")
        self.check(code.startswith("TAQA-WT-"), f"employee code auto-generated ({code})")
        self.ids["employee"] = body["id"]
        return body["id"]

    def test_employee_update(self):
        print("\n--- employee update ---")
        status, body = request("PUT", f"/api/employees/{self.ids['employee']}", {
            "status": "on_leave"
        })
        ok = expect_ok(status, body, "update employee status")
        self.check(ok, "update employee status")
        if ok:
            self.check(body.get("status") == "on_leave", "status reflected")

    def test_notes(self):
        print("\n--- notes ---")
        status, body = request("POST", "/api/notes", {
            "employee_id": self.ids["employee"],
            "body": "Smoke note"
        })
        ok = expect_ok(status, body, "create note")
        self.check(ok, "create note")
        if ok:
            self.ids["note"] = body["id"]
            data = bootstrap()
            notes = [n for n in data.get("notes", []) if n["id"] == self.ids["note"]]
            self.check(len(notes) == 1, "note appears in bootstrap")

    def test_documents(self):
        print("\n--- documents (with file upload) ---")
        status, body = request("POST", "/api/documents", {
            "employee_id": self.ids["employee"],
            "document_type_id": self.ids["doc_type"],
            "expiry_date": "2027-01-01",
            "reminder_days": 90,
            "file_base64": "data:application/pdf;base64,JVBERi0xLg==",
            "file_name": "smoke_doc.pdf"
        })
        ok = expect_ok(status, body, "create document")
        self.check(ok, "create document")
        if ok:
            self.ids["document"] = body["id"]
            self.check(body.get("status") == "valid", "document status computed")
            self.check(body.get("file_url", "").startswith("/uploads/"), "document file_url set")

    def test_certificates(self):
        print("\n--- certificates (with file upload) ---")
        status, body = request("POST", "/api/certificates", {
            "employee_id": self.ids["employee"],
            "certificate_type_id": self.ids["cert_type"],
            "expiry_date": "2027-01-01",
            "file_base64": "data:application/pdf;base64,JVBERi0xLg==",
            "file_name": "smoke_cert.pdf"
        })
        ok = expect_ok(status, body, "create certificate")
        self.check(ok, "create certificate")
        if ok:
            self.ids["certificate"] = body["id"]
            self.check(body.get("status") == "valid", "certificate status computed")
            self.check(body.get("file_url", "").startswith("/uploads/"), "certificate file_url set")

    def test_attachments(self):
        print("\n--- attachments (with file upload) ---")
        status, body = request("POST", "/api/attachments", {
            "employee_id": self.ids["employee"],
            "label": "Smoke attachment",
            "file_base64": "data:application/pdf;base64,JVBERi0xLg==",
            "file_name": "smoke_attachment.pdf"
        })
        ok = expect_ok(status, body, "create attachment")
        self.check(ok, "create attachment")
        if ok:
            self.ids["attachment"] = body["id"]
            self.check(body.get("file_url", "").startswith("/uploads/"), "attachment file_url set")

    def test_deployments_and_b2b(self):
        print("\n--- deployments & b2b (minimal UI payload) ---")
        status, body = request("POST", "/api/deployments", {
            "employee_id": self.ids["employee"],
            "client_id": self.ids["client"],
            "field_id": self.ids["field"],
            "project_id": self.ids["project"],
            "start_date": str(date.today()),
            "end_date": None,
            "rotation_duration_days": 28,
            "b2b_partner_employee_id": None,
            "status": "active",
            "notes": None
        })
        ok = expect_ok(status, body, "create deployment")
        self.check(ok, "create deployment")
        if not ok:
            return
        self.ids["deployment"] = body["id"]
        self.check(body.get("position_id") == self.ids["position"], "position_id filled from employee")
        self.check(body.get("status") == "active", "status preserved as active")
        empAfter = request("GET", f"/api/bootstrap")[1]
        empRow = next((e for e in empAfter.get("employees", []) if e["id"] == self.ids["employee"]), {})
        self.check(empRow.get("status") == "deployed", "employee status synced to deployed")

        data = bootstrap()
        b2b = [p for p in data.get("back_to_back_pairs", []) if p["deployment_id"] == self.ids["deployment"]]
        self.check(len(b2b) == 1, "b2b pair auto-created")
        if b2b:
            self.ids["b2b"] = b2b[0]["id"]
            status, body = request("PUT", f"/api/b2b/{self.ids['b2b']}", {
                "replacement_employee_id": self.ids["employee"],
                "status": "confirmed"
            })
            self.check(expect_ok(status, body, "update b2b"), "update b2b")

    def test_evaluations(self):
        print("\n--- evaluations ---")
        status, body = request("POST", "/api/evaluations", {
            "employee_id": self.ids["employee"],
            "template_id": "ev-t-1",
            "supervisor_name": "Smoke Supervisor",
            "period_start": str(date.today()),
            "period_end": str(date.today()),
            "competency_score": 80,
            "hse_score": 90,
            "training_score": 85,
            "attitude_score": 95,
            "would_rehire": True,
            "item_scores": {"evi-1": 4, "evi-2": 5, "evi-3": 4, "evi-4": 5}
        })
        ok = expect_ok(status, body, "create evaluation")
        self.check(ok, "create evaluation")
        if ok:
            eval_data = body.get("evaluation", body)
            self.ids["evaluation"] = eval_data["id"]
            self.check(eval_data.get("overall_score") == 87.5, f"overall_score computed ({eval_data.get('overall_score')})")

    def test_payroll(self):
        print("\n--- payroll ---")
        status, body = request("GET", "/api/payroll/2026-08")
        ok = expect_ok(status, body, "get payroll period")
        self.check(ok, "get payroll period")
        if ok:
            entries = body.get("entries", [])
            emp_entries = [e for e in entries if e.get("employee_id") == self.ids.get("employee")]
            self.check(len(emp_entries) == 1, "payroll entry generated for employee")

    def test_ai_scan(self):
        print("\n--- ai scan ---")
        status, body = request("POST", "/api/ai/scan", {})
        ok = expect_ok(status, body, "trigger ai scan")
        self.check(ok, "trigger ai scan")

    def test_employee_delete_cascade(self):
        print("\n--- employee delete cascade ---")
        status, body = request("DELETE", f"/api/employees/{self.ids['employee']}")
        ok = expect_ok(status, body, "delete employee")
        self.check(ok, "delete employee")
        if ok:
            data = bootstrap()
            self.check(self.ids["employee"] not in [e["id"] for e in data.get("employees", [])], "employee removed")
            if "note" in self.ids:
                self.check(self.ids["note"] not in [n["id"] for n in data.get("notes", [])], "notes cascaded")
            if "document" in self.ids:
                self.check(self.ids["document"] not in [d["id"] for d in data.get("documents", [])], "documents cascaded")
            if "certificate" in self.ids:
                self.check(self.ids["certificate"] not in [c["id"] for c in data.get("certificates", [])], "certificates cascaded")
            if "attachment" in self.ids:
                self.check(self.ids["attachment"] not in [a["id"] for a in data.get("attachments", [])], "attachments cascaded")
            if "deployment" in self.ids:
                self.check(self.ids["deployment"] not in [d["id"] for d in data.get("deployments", [])], "deployments cascaded")

    def cleanup(self):
        print("\n--- cleanup ---")
        # employee already deleted in the cascade test
        for key in ["project", "field", "position", "department", "client",
                    "doc_type", "cert_type", "equipment_type", "leave_type",
                    "shift_type", "rotation_type", "location"]:
            rid = self.ids.get(key)
            if not rid:
                continue
            if key in ("position", "department", "client", "field", "project",
                       "doc_type", "cert_type", "equipment_type", "leave_type",
                       "shift_type", "rotation_type", "location"):
                table = key if key != "location" else "locations"
                if key == "doc_type":
                    table = "document_types"
                elif key == "cert_type":
                    table = "certificate_types"
                elif key == "equipment_type":
                    table = "equipment_types"
                elif key == "leave_type":
                    table = "leave_types"
                elif key == "shift_type":
                    table = "shift_types"
                elif key == "rotation_type":
                    table = "rotation_types"
                request("DELETE", f"/api/settings/{table}/{rid}")

        # remove uploaded smoke test files
        import glob, os
        for f in glob.glob("public/uploads/*_smoke*.pdf"):
            os.remove(f)

    def run(self):
        self.setup()
        self.test_employee_auto_code()
        self.test_employee_update()
        self.test_notes()
        self.test_documents()
        self.test_certificates()
        self.test_attachments()
        self.test_deployments_and_b2b()
        self.test_evaluations()
        self.test_payroll()
        self.test_ai_scan()
        self.test_employee_delete_cascade()
        self.cleanup()
        print(f"\nRESULT: {self.passed} passed, {self.failed} failed")
        return self.failed == 0


if __name__ == "__main__":
    ok = TestRunner().run()
    sys.exit(0 if ok else 1)
