<?php
// plugins/intranet/front/news.view.php
include('../../../inc/includes.php');

// Visualização pública (sem exigir permissão explícita).
global $CFG_GLPI, $DB;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
   Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/dashboard.php');
   exit;
}

/* ================= Helpers ================= */
function intranet_current_origin(): string {
   // respeita proxy/reverso quando existir
   $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
      ? $_SERVER['HTTP_X_FORWARDED_PROTO']
      : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

   $host = !empty($_SERVER['HTTP_X_FORWARDED_HOST'])
      ? $_SERVER['HTTP_X_FORWARDED_HOST']
      : (!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'] ?? 'localhost'));

   if (strpos($host, ':') === false && !empty($_SERVER['SERVER_PORT'])) {
      $port = (string)$_SERVER['SERVER_PORT'];
      $is_default = ($proto === 'https' && $port === '443') || ($proto === 'http' && $port === '80');
      if (!$is_default) {
         $host .= ':' . $port;
      }
   }
   return $proto.'://'.$host;
}

function intranet_abs_url(string $path): string {
   return rtrim(intranet_current_origin(), '/') . $path;
}

/* =============== Busca notícia =============== */
$item = null;
try {
   $sql = "
      SELECT n.*,
             c.name  AS cat_name,
             u.name  AS user_login,
             u.realname,
             u.firstname
      FROM glpi_intranet_news n
      LEFT JOIN glpi_intranet_news_categories c ON c.id = n.category_id
      LEFT JOIN glpi_users u ON u.id = n.users_id
      WHERE n.id = ".(int)$id."
      LIMIT 1
   ";
   foreach ($DB->request($sql) as $r) { $item = $r; break; }
} catch (Throwable $e) {
   Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
}

if (!$item) {
   Html::header(__('Notícia','intranet'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginIntranetMenu');
   echo '<div class="center">'. __('Notícia não encontrada.','intranet') .'</div>';
   Html::footer();
   exit;
}

/* =============== Autor / Datas / Categoria =============== */
$author = '—';
if (!empty($item['realname']) || !empty($item['firstname'])) {
   $parts = [];
   if (!empty($item['firstname'])) $parts[] = $item['firstname'];
   if (!empty($item['realname']))  $parts[] = $item['realname'];
   $author = trim(implode(' ', $parts));
} elseif (!empty($item['user_login'])) {
   $author = $item['user_login'];
}

$pub  = !empty($item['date_publication']) ? date('d/m/Y H:i', strtotime($item['date_publication'])) : '';
$exp  = !empty($item['date_expiration'])  ? date('d/m/Y H:i', strtotime($item['date_expiration']))  : '';
$cat  = !empty($item['cat_name']) ? $item['cat_name'] : '—';

/* =============== URL absoluta para compartilhar =============== */
$shareURL = intranet_abs_url($CFG_GLPI['root_doc'].'/plugins/intranet/front/news.view.php?id='.$id);

/* =============== Cabeçalho GLPI / CSS =============== */
Html::header(__('Notícia','intranet'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginIntranetMenu');

$cssAbs = __DIR__.'/../assets/intranet.css';
echo '<link rel="stylesheet" href="'.$CFG_GLPI['root_doc'].'/plugins/intranet/assets/intranet.css?v='.(filemtime($cssAbs)?:time()).'">';
?>
<style>
/* Escopo local */
.intranet.news-view { max-width: 1100px; margin: 16px auto; }

.news-actions { display:flex; gap:10px; align-items:center; margin: 0 0 14px; }
.news-actions .btn {
  display:inline-block; background:#0b2a5a; color:#fff; text-decoration:none;
  padding:8px 12px; border-radius:8px; font-weight:600;
}
.news-actions .btn:hover { background:#0a1f42; }

.card { background:#fff; border:1px solid #cfd8ea; border-radius:14px; padding:16px; }

/* === Banner 100% da ALTURA do wrapper === */
:root { --banner-h: 60vh; } /* ajuste como preferir: 40vh / 100vh */
@media (max-width: 768px){ :root { --banner-h: 40vh; } }

.intranet.news-view .banner-wrap{
  width:100%;
  height:var(--banner-h);
  border-radius:10px;
  overflow:hidden;
  margin-bottom:16px;
  background:#f3f4f6;
}

/* Ganha a disputa contra estilos globais do GLPI (img {height:auto}) */
.intranet.news-view .banner-wrap .banner{
  width:100% !important;
  height:100% !important;
  max-height:none !important;
  display:block;
  object-fit:cover;
  object-position:center;
}

.title {
  font-size: 28px;
  line-height: 1.15;
  color:#0b2a5a;
  font-weight: 700;
  margin: 0 0 8px;
}

.meta { display:flex; flex-wrap:wrap; gap:18px; color:#6b7280; font-size:14px; margin-bottom:12px; }
.meta .label { color:#374151; font-weight:600; margin-right:4px; }

.content { font-size:16px; color:#111827; line-height:1.7; margin-bottom: 18px; }
.content p { margin: 0 0 12px; }

.news-footer{ display:flex; flex-direction:column; align-items:flex-start; gap:8px; margin-top: 12px; }
.news-footer .created-by { color:#374151; }
.news-footer .share .btn { background:#0b2a5a; }
.news-footer .note { font-size:13px; color:#6b7280; margin: 4px 0 0; }
</style>

<div class="intranet news-view">
  <div class="news-actions">
    <a class="btn" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/dashboard.php">← Início</a>
  </div>

  <div class="card">
    <?php if (!empty($item['banner'])): ?>
      <div class="banner-wrap">
        <img class="banner"
             style="width:100%;height:100%;object-fit:cover;object-position:center;display:block"
             src="<?php echo Html::cleanInputText($item['banner']); ?>" alt="Banner">
      </div>
    <?php endif; ?>

    <h1 class="title"><?php echo Html::entities_deep($item['title']); ?></h1>

    <div class="meta">
      <?php if ($pub): ?>
        <div><span class="label">Publicação:</span><?php echo Html::entities_deep($pub); ?></div>
      <?php endif; ?>

      <?php if ($exp): ?>
        <div><span class="label">Expira:</span><?php echo Html::entities_deep($exp); ?></div>
      <?php endif; ?>

      <div><span class="label">Categoria:</span><?php echo Html::entities_deep($cat); ?></div>
    </div>

    <div class="content">
      <?php echo (string)$item['content']; ?>
    </div>

    <div class="news-footer">
      <div class="created-by"><strong>Criado por:</strong> <?php echo Html::entities_deep($author); ?></div>

      <div class="share">
        <button id="copyLink" class="btn" type="button">📋 Copiar link</button>
      </div>

      <p class="note">
        Informação interna do Grupo Bringel, sujeita a confidencialidade. Qualquer divulgação a terceiros exige autorização. Em caso de dados pessoais, cumprir a LGPD e as diretrizes internas de privacidade e segurança.
      </p>
    </div>
  </div>
</div>

<script>
(function(){
  var btn = document.getElementById('copyLink');
  if (!btn) return;
  var link = <?php echo json_encode($shareURL, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

  btn.addEventListener('click', async function(){
    try {
      await navigator.clipboard.writeText(link);
      var original = btn.textContent;
      btn.textContent = '✅ Copiado!';
      setTimeout(function(){ btn.textContent = original; }, 1500);
    } catch(e) {
      var ta = document.createElement('textarea');
      ta.value = link; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      var original = btn.textContent;
      btn.textContent = '✅ Copiado!';
      setTimeout(function(){ btn.textContent = original; }, 1500);
    }
  });
})();
</script>

<?php Html::footer();
