<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>

<div id="container" class="container" >
    <div class="col-md-12 col-lg-12 settings-content" style="max-height: 500px; overflow-y: auto;">
        <h1 class="h3 mb-4"><i class="fas fa-cog me-2 text-primary"></i>Paramètres</h1>

        <div class="row g-4" >
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Informations de l'établissement</strong></div>
                    <div class="card-body">
                        <form id="businessForm" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <div class="col-12">
                                <label class="form-label">Nom</label>
                                <input class="form-control" name="business_name" id="business_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input class="form-control" name="business_email" id="business_email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone</label>
                                <input class="form-control" name="business_phone" id="business_phone">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="business_address" id="business_address" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Horaires</label>
                                <textarea class="form-control" name="business_hours" id="business_hours" rows="2"></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Sauvegarder</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Emails / SMTP</strong></div>
                    <div class="card-body">
                        <form id="smtpForm" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <div class="col-12">
                                <label class="form-label">Activer l'envoi d'emails</label>
                                <select class="form-select" name="smtp_enabled" id="smtp_enabled">
                                    <option value="0">Désactivé (mode DEV/log)</option>
                                    <option value="1">Activé</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Fournisseur</label>
                                <select class="form-select" name="smtp_provider" id="smtp_provider">
                                    <option value="dev_log">DEV (log)</option>
                                    <option value="php_smtp">PHP SMTP (Windows)</option>
                                    <option value="sendgrid">SendGrid (API)</option>
                                    <option value="mailgun">Mailgun (API)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">De (email)</label>
                                <input class="form-control" name="smtp_from_email" id="smtp_from_email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">De (nom)</label>
                                <input class="form-control" name="smtp_from_name" id="smtp_from_name">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Hôte SMTP</label>
                                <input class="form-control" name="smtp_host" id="smtp_host" placeholder="Pour PHP SMTP (Windows)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Port</label>
                                <input class="form-control" name="smtp_port" id="smtp_port" placeholder="25/587/465">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sécurité</label>
                                <select class="form-select" name="smtp_secure" id="smtp_secure">
                                    <option value="">Aucune</option>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Utilisateur</label>
                                <input class="form-control" name="smtp_user" id="smtp_user">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mot de passe / Clé API</label>
                                <input class="form-control" name="smtp_pass" id="smtp_pass">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Clé API (SendGrid/Mailgun)</label>
                                <input class="form-control" name="smtp_api_key" id="smtp_api_key">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Domaine (Mailgun)</label>
                                <input class="form-control" name="smtp_api_domain" id="smtp_api_domain">
                            </div>
                            <div class="col-12 d-flex gap-2 justify-content-end">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Sauvegarder</button>
                                <button class="btn btn-outline-secondary" type="button" id="btnTest"><i class="fas fa-paper-plane me-1"></i>Test email</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const businessForm = document.getElementById('businessForm');
        const smtpForm = document.getElementById('smtpForm');
        const btnTest = document.getElementById('btnTest');

        function load() {
            fetch('api/settings.php?action=get', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json()).then(res => {
                    if (!res.success) throw new Error(res.message || 'Erreur');
                    const s = res.data || {};
                    for (const [k,v] of Object.entries(s)) {
                        const el1 = businessForm.querySelector('[name="' + k + '"]');
                        const el2 = smtpForm.querySelector('[name="' + k + '"]');
                        if (el1) el1.value = v;
                        if (el2) el2.value = v;
                    }
                }).catch(err => alert(err.message));
        }

        function save(form) {
            const fd = new FormData(form);
            fd.append('action', 'save');
            fetch('api/settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message || 'Erreur'); alert('Paramètres sauvegardés'); })
                .catch(err => alert(err.message));
        }

        businessForm.addEventListener('submit', (e) => { e.preventDefault(); save(businessForm); });
        smtpForm.addEventListener('submit', (e) => { e.preventDefault(); save(smtpForm); });

        btnTest.addEventListener('click', () => {
            const to = prompt("Envoyer un email de test à:");
            if (!to) return;
            const fd = new FormData(smtpForm);
            fd.append('action', 'test_email');
            fd.append('to', to);
            fetch('api/settings.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json()).then(res => { alert(res.message || (res.success ? 'OK' : 'Échec')); })
                .catch(err => alert(err.message));
        });

        load();
    </script>
</body>
</html>


