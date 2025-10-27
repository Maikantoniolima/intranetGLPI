<?php
// plugins/intranet/front/config.php
// GUI com duas abas:
// 1) Banner: POST (sem CSRF, conforme seu fluxo que funcionou) para ESTA MESMA PÁGINA -> salva ../banner/banner.jpg -> redirect GET ?salvar=1&banner=...
// 2) Dados : GET (?salvar=1) para persistir no banco (estilo OS / norma do projeto)

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

global $CFG_GLPI, $DB;

// =======================
// Caminhos do banner (iguais ao que funcionou no insert standalone)
// =======================
$destDirAbs = realpath(__DIR__ . '/..') . '/banner';   // .../plugins/intranet/banner
$destFile   = $destDirAbs . '/banner.jpg';
$publicURL  = $CFG_GLPI['root_doc'] . '/plugins/intranet/banner/banner.jpg';

// Garante a pasta
if (!is_dir($destDirAbs)) {
   @mkdir($destDirAbs, 0775, true);
}

// =======================
// POST (upload do banner) — processado AQUI, sem CSRF (como o código que funcionou)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {

   $msg = '';
   if (isset($_FILES['arquivo']['name']) && $_FILES['arquivo']['error'] == 0) {

      $arquivo_tmp = $_FILES['arquivo']['tmp_name'];
      $nome        = $_FILES['arquivo']['name'];

      // valida extensão .jpg (simples, como no OS)
      $extensao = strrchr($nome, '.');
      $extensao = strtolower($extensao);

      if (strstr('.jpg;.jpeg', $extensao)) {

         if (!is_writable($destDirAbs)) {
            $msg = 'Erro: pasta de destino não é gravável: ' . Html::entities_deep($destDirAbs);
         } else {
            // remove anterior e grava novo
            if (is_file($destFile)) { @unlink($destFile); }

            if (@move_uploaded_file($arquivo_tmp, $destFile)) {
               @chmod($destFile, 0644);

               // cache-busting
               $urlWithV = $publicURL . '?v=' . time();

               // Redireciona para persistir no banco via GET (norma do projeto)
               $redirect = $CFG_GLPI['root_doc']
                         . '/plugins/intranet/front/config.php'
                         . '?salvar=1'
                         . '&banner=' . urlencode($urlWithV);

               header('Location: ' . $redirect);
               exit;

            } else {
               $msg = 'Erro ao salvar o arquivo. Aparentemente você não tem permissão de escrita.';
            }
         }
      } else {
         $msg = 'Você poderá enviar apenas arquivos ".jpg" ou ".jpeg"';
      }

   } else {
      $msg = 'Você não enviou nenhum arquivo!';
   }

   // se chegou aqui, houve erro de upload -> mostra mensagem e recarrega
   Session::addMessageAfterRedirect('❌ '.$msg, false, ERROR);
   Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/config.php');
   exit;
}

// =======================
// GET (salvar campos) — estilo OS com GET (ATUALIZA SOMENTE O QUE VEIO)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvar'])) {
   try {
      // Campos aceitáveis
      $fields = [
         'banner',
         'btn1_label','btn1_link',
         'btn2_label','btn2_link',
         'btn3_label','btn3_link',
         'weather_city','weather_api_key'
      ];

      // Monta $dados somente com o que veio na querystring (não zera os demais)
      $dados = [];
      foreach ($fields as $f) {
         if (array_key_exists($f, $_GET)) {
            $dados[$f] = trim($_GET[$f] ?? '');
         }
      }

      if (empty($dados)) {
         Session::addMessageAfterRedirect('ℹ️ Nada para salvar.', false, INFO);
         Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/config.php');
         exit;
      }

      // Upsert id=1 — atualiza só os campos presentes
      $exists = $DB->request([
         'FROM'  => 'glpi_intranet_config',
         'WHERE' => ['id' => 1],
         'LIMIT' => 1
      ])->count() > 0;

      $ok = $exists
         ? $DB->update('glpi_intranet_config', $dados, ['id' => 1])
         : $DB->insert('glpi_intranet_config', ['id' => 1] + $dados);

      Session::addMessageAfterRedirect(
         $ok ? '✅ Configurações salvas!' : '❌ Erro ao salvar: '.$DB->error(),
         false,
         $ok ? INFO : ERROR
      );

   } catch (Throwable $e) {
      Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
   }

   Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/config.php');
   exit;
}

// =======================
// GET (carregar config)
// =======================
$config = [];
try {
   $res = $DB->request([
      'FROM'  => 'glpi_intranet_config',
      'WHERE' => ['id' => 1],
      'LIMIT' => 1
   ]);
   foreach ($res as $row) { $config = $row; break; }

   if (empty($config)) {
      $config = [
         'banner'          => '',
         'btn1_label'      => 'Política de Segurança',
         'btn1_link'       => '',
         'btn2_label'      => 'Portal RH',
         'btn2_link'       => '',
         'btn3_label'      => 'Acesso Rápido',
         'btn3_link'       => '',
         'weather_city'    => 'Manaus,BR',
         'weather_api_key' => ''
      ];
   }
} catch (Throwable $e) {
   $config = [
      'banner'          => '',
      'btn1_label'      => '',
      'btn1_link'       => '',
      'btn2_label'      => '',
      'btn2_link'       => '',
      'btn3_label'      => '',
      'btn3_link'       => '',
      'weather_city'    => '',
      'weather_api_key' => ''
   ];
}

// =======================
// UI
// =======================
Html::header('Configurações - Intranet', $_SERVER['PHP_SELF'], 'config', 'plugins');
echo '<link rel="stylesheet" href="' . $CFG_GLPI['root_doc'] . '/plugins/intranet/assets/intranet.css">';

$action_url = $CFG_GLPI['root_doc'].'/plugins/intranet/front/config.php';
?>
<style>
  .tabs { margin-top: 10px; }
  .tab-nav { display:flex; gap:8px; margin:10px 0 0; padding:0; list-style:none; }
  .tab-nav li { padding:8px 14px; background:#e9eef7; border-radius:6px 6px 0 0; cursor:pointer; border:1px solid #cfd8ea; border-bottom:none; }
  .tab-nav li.active { background:#fff; font-weight:600; }
  .tab-pane { display:none; border:1px solid #cfd8ea; border-radius:0 8px 8px 8px; padding:16px; background:#fff; }
  .tab-pane.active { display:block; }
  .tab-title { font-weight:600; margin:0 0 10px; }
  .hint { font-size:12px; color:#666; }
</style>

<?php echo '<div class="intranet">'; // [SCOPE] ABRE o escopo do plugin ?>

<div style="max-width:1000px; margin:20px auto; padding:20px;">
  <div class="center"><h2>⚙️ Configurações da Intranet</h2></div>

  <ul class="tab-nav" id="tabNav">
    <li class="active" data-tab="tab-banner">Banner</li>
    <li data-tab="tab-dados">Dados</li>
  </ul>

  <!-- ========== ABA 1: Banner (POST -> ESTA MESMA PÁGINA; persiste via GET após redirect) ========== -->
  <div class="tab-pane active" id="tab-banner">
    <div class="tab-title">🖼️ Banner</div>

    <table class="tab_cadre_fixe" style="margin-bottom:12px;">
      <tr class="tab_bg_1">
        <td style="width:30%;">Banner atual:</td>
        <td>
          <?php if (!empty($config['banner'])): ?>
            <img src="<?php echo Html::cleanInputText($config['banner']); ?>" alt="Banner"
                 style="max-width:100%; height:auto; border:1px solid #ddd; border-radius:6px;">
          <?php else: ?>
            <span class="small">Nenhum banner configurado.</span>
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <form method="post"
          action="<?php echo Html::cleanInputText($action_url); ?>"
          enctype="multipart/form-data"
          style="display:flex; gap:10px; align-items:center;">
      <input type="file" name="arquivo" accept=".jpg,.jpeg" required>
      <button type="submit" class="btn">Enviar</button>
      <span class="hint">Apenas JPG. Subir novo apaga o anterior. Após o upload, a URL é gravada via GET.</span>
    </form>
  </div>

  <!-- ========== ABA 2: Dados (GET -> config.php?salvar=1) ========== -->
  <div class="tab-pane" id="tab-dados">
    <div class="tab-title">🧩 Dados da Intranet</div>
    <form method="get" action="<?php echo Html::cleanInputText($action_url); ?>">
      <table class="tab_cadre_fixe">
        <tr><th colspan="2" style="background:#0b2a5a; color:#fff;">🔘 Botões</th></tr>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr class="tab_bg_<?php echo ($i % 2 == 0) ? '2' : '1'; ?>">
          <td style="width:30%;">Botão <?php echo $i; ?>:</td>
          <td>
            <input type="text" name="btn<?php echo $i; ?>_label" placeholder="Nome"
                   value="<?php echo Html::cleanInputText($config["btn{$i}_label"] ?? ''); ?>"
                   style="width:30%; padding:6px; margin-right:10px;">
            <input type="text" name="btn<?php echo $i; ?>_link" placeholder="https://..."
                   value="<?php echo Html::cleanInputText($config["btn{$i}_link"] ?? ''); ?>"
                   style="width:55%; padding:6px;">
          </td>
        </tr>
        <?php endfor; ?>

        <tr><th colspan="2" style="background:#0b2a5a; color:#fff;">🌤️ Clima</th></tr>
        <tr class="tab_bg_1">
          <td style="width:30%;">Cidade:</td>
          <td>
            <input type="text" name="weather_city" placeholder="Manaus,BR"
                   value="<?php echo Html::cleanInputText($config['weather_city'] ?? ''); ?>"
                   style="width:90%; padding:6px;">
          </td>
        </tr>
        <tr class="tab_bg_2">
          <td>API Key:</td>
          <td>
            <input type="text" name="weather_api_key"
                   value="<?php echo Html::cleanInputText($config['weather_api_key'] ?? ''); ?>"
                   style="width:90%; padding:6px;">
          </td>
        </tr>

        <tr class="tab_bg_2">
          <td class="center" colspan="2">
            <button type="submit" name="salvar" value="1" class="vsubmit">💾 Salvar</button>
          </td>
        </tr>
      </table>
    </form>
  </div>
</div>

<script>
  (function() {
    var nav = document.getElementById('tabNav');
    if (!nav) return;
    nav.addEventListener('click', function(e) {
      var li = e.target.closest('li[data-tab]');
      if (!li) return;
      var tabId = li.getAttribute('data-tab');
      nav.querySelectorAll('li').forEach(function(item){ item.classList.toggle('active', item===li); });
      document.querySelectorAll('.tab-pane').forEach(function(pane){
        pane.classList.toggle('active', pane.id === tabId);
      });
    });
  })();
</script>

<?php
echo '</div>'; // [SCOPE] FECHA o escopo do plugin
Html::footer();
