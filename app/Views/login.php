<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - MedStock</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/medstock.css">
  <style>[v-cloak] { display: none !important; }</style>
</head>
<body>
  <div id="app" v-cloak class="container py-4" style="max-width: 1000px">


    <p v-if="isCheckingSession" class="text-muted">Memuat...</p>

    <section v-else class="ms-card ms-login">
      <div class="ms-card-body">
        <h1 class="h5 fw-bold mb-1 text-center">Masuk ke MedStock</h1>
        <p class="small text-muted mb-4 text-center">Gunakan akun yang diberikan oleh admin.</p>
        <div v-if="loginError" class="alert alert-danger py-2">{{ loginError }}</div>
        <form @submit.prevent="login">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" v-model="loginForm.email" required>
            <div v-if="loginFieldErrors.email" class="text-danger small mt-1">{{ loginFieldErrors.email }}</div>
          </div>
          <div class="mb-4">
            <label class="form-label">Kata sandi</label>
            <input type="password" class="form-control" v-model="loginForm.password" required>
            <div v-if="loginFieldErrors.password" class="text-danger small mt-1">{{ loginFieldErrors.password }}</div>
          </div>
          <button class="btn btn-primary w-100" :disabled="isLoggingIn">
            {{ isLoggingIn ? 'Memproses...' : 'Masuk' }}
          </button>
        </form>
      </div>
    </section>
  </div>

  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
  <script src="/js/api.js"></script>
  <script src="/js/login.js"></script>
</body>
</html>
