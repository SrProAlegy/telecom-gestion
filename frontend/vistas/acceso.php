<section class="login-shell" aria-labelledby="login-title">
    <div class="login-brand"><span class="brand-mark">T</span><strong>TELCOM<span>GESTIÓN</span></strong><p>Sistema web de gestión</p><div class="phone-art" aria-hidden="true"><?= icono('conexion') ?></div></div>
    <div class="login-main"><form class="login-card" action="/api/sesion" method="post" data-api-form data-login>
        <p class="login-logo">TELCOM <span>GESTIÓN</span></p><h1 id="login-title">Iniciar sesión</h1><p class="muted">Ingresa tus datos para continuar.</p>
        <div class="form-message" role="alert" hidden></div>
        <label for="correo-acceso">Correo electrónico</label><input id="correo-acceso" name="correo" type="email" autocomplete="username" placeholder="nombre@correo.com" maxlength="254" required>
        <label for="clave-acceso">Contraseña</label><div class="password-field"><input id="clave-acceso" name="password" type="password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password="clave-acceso" aria-pressed="false">Mostrar</button></div>
        <button class="btn btn-primary login-button" type="submit">Entrar <?= icono('flecha') ?></button>
        <p class="login-note">Acceso para personal de Telcom.</p>
    </form></div>
</section>
