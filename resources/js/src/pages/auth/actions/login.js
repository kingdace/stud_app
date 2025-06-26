import { reactive, ref } from "vue";
import { makeHttpReq } from "../../../helper/makeHttpReq";
import { successMsg } from "../../../helper/toast-notification";
import { showErrorResponse } from "../../../helper/util";

// Define loginInput without TypeScript types
export const loginInput = reactive({
    email: "",
    password: "",
});

export function useLoginUser() {
    const loading = ref(false);

    async function login() {
        try {
            loading.value = true;

            // First try regular login
            let data = await makeHttpReq("login", "POST", loginInput);

            // If regular login fails, try member login
            if (!data.isLoggedIn) {
                data = await makeHttpReq("member-login", "POST", loginInput);
            }

            loading.value = false;

            if (data.isLoggedIn) {
                loginInput.email = "";
                loginInput.password = "";
                successMsg(data.message);
                localStorage.setItem("userData", JSON.stringify(data));
                window.location.href = "/app/admin";
            } else {
                showErrorResponse({ message: "Invalid email or password" });
            }
        } catch (error) {
            loading.value = false;
            console.error("Login error:", error);
            showErrorResponse(error);
        }
    }

    return { login, loading };
}
