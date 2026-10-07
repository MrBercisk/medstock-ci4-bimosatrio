const { createApp, ref, reactive, onMounted } = Vue;

createApp({
  setup() {
    const isCheckingSession = ref(true);

    const loginForm = reactive({ email: '', password: '' });
    const isLoggingIn = ref(false);
    const loginError = ref('');
    const loginFieldErrors = ref({});

    async function login() {
      isLoggingIn.value = true;
      loginError.value = '';
      loginFieldErrors.value = {};

      const result = await apiRequest('/login', { method: 'POST', body: loginForm });

      if (result.ok) {
        // Pindah ke halaman utama; di sana identitas diambil dari server (/me).
        window.location.replace('/');
        return;
      }

      loginError.value = result.body.message || 'Login gagal.';
      loginFieldErrors.value = result.body.errors || {};
      isLoggingIn.value = false;
    }

    // Kalau sudah login, tidak perlu melihat halaman login lagi.
    onMounted(async () => {
      const result = await apiRequest('/me');
      if (result.ok) {
        window.location.replace('/');
        return;
      }
      isCheckingSession.value = false;
    });

    return { isCheckingSession, loginForm, isLoggingIn, loginError, loginFieldErrors, login };
  },
}).mount('#app');
