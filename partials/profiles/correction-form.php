<?php
declare(strict_types=1);
if(!isset($correctionForm)||!is_array($correctionForm)){http_response_code(403);exit;}
?>
<form data-correction-report method="post" action="/api/profiles/public/correction-reports.php" autocomplete="off" aria-describedby="mxpp-correction-privacy">
  <input type="hidden" name="intent" value="<?= h($correctionForm['intent']) ?>">
  <input type="hidden" name="csrf_token" value="<?= h($correctionForm['csrf_token']) ?>">
  <fieldset>
    <legend>Informar cambios de este perfil</legend>
    <label for="mxpp-correction-category">Tipo de corrección *</label>
    <select id="mxpp-correction-category" name="category" required>
      <option value="">Selecciona una opción</option>
      <?php foreach(\Profiles\Corrections\ReportCatalog::CATEGORIES as $key=>$label): ?>
      <option value="<?= h($key) ?>"><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="mxpp-correction-details">¿Qué información ha cambiado? *</label>
    <textarea id="mxpp-correction-details" name="explanation" rows="3" minlength="20" maxlength="2000" required aria-describedby="mxpp-correction-limit"></textarea>
    <p id="mxpp-correction-limit" class="mxpp-correction__note">Entre 20 y 2000 caracteres. No incluyas información clínica, de pacientes ni documentos sensibles.</p>
    <label for="mxpp-correction-relation">Relación con el profesional (opcional)</label>
    <select id="mxpp-correction-relation" name="relation">
      <option value="">Prefiero no indicarla</option>
      <?php foreach(\Profiles\Corrections\ReportCatalog::RELATIONS as $key=>$label): ?>
      <option value="<?= h($key) ?>"><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="mxpp-correction-contact">Correo para aclaraciones (opcional)</label>
    <input id="mxpp-correction-contact" name="contact" type="email" maxlength="254" autocomplete="off">
    <p id="mxpp-correction-privacy" class="mxpp-correction__note">Usaremos tu explicación y, si lo compartes, tu correo únicamente para revisar este informe y posibles aclaraciones. No se publicarán. El informe no acredita una relación ni concede acceso al perfil.</p>
    <label class="mxpp-correction__ack"><input name="privacy_ack" type="checkbox" required> Entiendo que la información requiere revisión y acepto este uso limitado de mis datos. *</label>
    <button type="submit">Enviar informe</button>
  </fieldset>
  <p id="mxpp-correction-status" class="mxpp-correction__notice" role="status" aria-live="polite"></p>
</form>
<script src="/assets/js/public-profile-correction.js" defer></script>
