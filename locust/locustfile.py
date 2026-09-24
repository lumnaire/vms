from locust import HttpUser, task, between
from bs4 import BeautifulSoup


class BaseLaravelUser(HttpUser):
    wait_time = between(1, 3)

    username = ""
    password = ""

    def on_start(self):
        # ---------------------------------------------------------
        # STEP 1: GET LOGIN PAGE
        # ---------------------------------------------------------
        login_page = self.client.get(
            "/login",
            name="AUTH - GET /login",
        )

        if login_page.status_code != 200:
            print(
                f"[LOGIN PAGE FAILED] {self.username} "
                f"HTTP {login_page.status_code}"
            )
            return

        # ---------------------------------------------------------
        # STEP 2: EXTRACT CSRF TOKEN
        # ---------------------------------------------------------
        soup = BeautifulSoup(login_page.text, "html.parser")

        csrf_input = soup.find(
            "input",
            {"name": "_token"}
        )

        if not csrf_input:
            print(
                f"[CSRF FAILED] No CSRF token found for "
                f"{self.username}"
            )
            return

        csrf_token = csrf_input.get("value")

        # ---------------------------------------------------------
        # STEP 3: SUBMIT LOGIN
        # ---------------------------------------------------------
        response = self.client.post(
            "/login",
            data={
                "_token": csrf_token,
                "username": self.username,
                "password": self.password,
                "remember": "",
            },
            name="AUTH - POST /login",
            allow_redirects=False,
        )

        # ---------------------------------------------------------
        # STEP 4: CHECK LOGIN RESPONSE
        # ---------------------------------------------------------
        print(
            f"[LOGIN] {self.username} | "
            f"HTTP {response.status_code} | "
            f"Location: {response.headers.get('Location')}"
        )

        if response.status_code not in [302, 303]:
            print(
                f"[LOGIN FAILED] {self.username} "
                f"returned HTTP {response.status_code}"
            )

            print(
                f"[LOGIN RESPONSE] "
                f"{response.text[:300]}"
            )

            return

        # ---------------------------------------------------------
        # STEP 5: FOLLOW REDIRECT MANUALLY
        # ---------------------------------------------------------
        redirect_url = response.headers.get("Location")

        if redirect_url:
            dashboard_response = self.client.get(
                redirect_url,
                name=f"AUTH - {redirect_url}",
            )

            print(
                f"[AUTH CHECK] {self.username} | "
                f"{redirect_url} | "
                f"HTTP {dashboard_response.status_code}"
            )

            # If Laravel redirects us back to login,
            # authentication did not persist.
            if "/login" in dashboard_response.url:
                print(
                    f"[AUTH FAILED] {self.username} "
                    f"was redirected back to /login"
                )
            else:
                print(
                    f"[AUTH SUCCESS] {self.username} "
                    f"is authenticated"
                )


# ================================================================
# VENDOR
# ================================================================

class VendorUser(BaseLaravelUser):

    username = "loadtest_vendor"
    password = "TEST_PASSWORD"

    @task(5)
    def dashboard(self):
        self.client.get(
            "/vendor/dashboard",
            name="VENDOR - GET /vendor/dashboard",
        )

    @task(5)
    def inventory(self):
        self.client.get(
            "/vendor/inventory",
            name="VENDOR - GET /vendor/inventory",
        )


# ================================================================
# STAFF
# ================================================================

class StaffUser(BaseLaravelUser):

    username = "loadtest_staff"
    password = "TEST_PASSWORD"

    @task(5)
    def dashboard(self):
        self.client.get(
            "/staff/dashboard",
            name="STAFF - GET /staff/dashboard",
        )

    @task(4)
    def confirmations(self):
        self.client.get(
            "/staff/confirmations",
            name="STAFF - GET /staff/confirmations",
        )

    @task(3)
    def price_guides(self):
        self.client.get(
            "/staff/price-guides",
            name="STAFF - GET /staff/price-guides",
        )

    @task(2)
    def reports(self):
        self.client.get(
            "/staff/reports",
            name="STAFF - GET /staff/reports",
        )

    @task(3)
    def vendors(self):
        self.client.get(
            "/staff/vendors",
            name="STAFF - GET /staff/vendors",
        )


# ================================================================
# SUPERVISOR
# ================================================================

class SupervisorUser(BaseLaravelUser):

    username = "loadtest_supervisor"
    password = "TEST_PASSWORD"

    @task(5)
    def dashboard(self):
        self.client.get(
            "/supervisor/dashboard",
            name="SUPERVISOR - GET /supervisor/dashboard",
        )

    @task(3)
    def fish_types(self):
        self.client.get(
            "/supervisor/fish-types",
            name="SUPERVISOR - GET /supervisor/fish-types",
        )

    @task(3)
    def forecasts(self):
        self.client.get(
            "/supervisor/forecasts",
            name="SUPERVISOR - GET /supervisor/forecasts",
        )

    @task(3)
    def price_guides(self):
        self.client.get(
            "/supervisor/price-guides",
            name="SUPERVISOR - GET /supervisor/price-guides",
        )

    @task(3)
    def reports(self):
        self.client.get(
            "/supervisor/reports",
            name="SUPERVISOR - GET /supervisor/reports",
        )

    @task(2)
    def staff(self):
        self.client.get(
            "/supervisor/staff",
            name="SUPERVISOR - GET /supervisor/staff",
        )

    @task(3)
    def vendors(self):
        self.client.get(
            "/supervisor/vendors",
            name="SUPERVISOR - GET /supervisor/vendors",
        )

    @task(1)
    def account(self):
        self.client.get(
            "/supervisor/account",
            name="SUPERVISOR - GET /supervisor/account",
        )