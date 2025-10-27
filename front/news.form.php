<?php
// plugins/intranet/front/news.form.php
include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

global $CFG_GLPI, $DB;

/* ============================================================
   CONTEXTO (pega id antes para usar no POST redirect)
============================================================ */
$editId = (int)($_GET['id'] ?? 0);

/* ============================================================
   UPLOADS (igual ao config, apenas destino/nomes)
============================================================ */
$destDirAbs   = realpath(__DIR__ . '/..') . '/uploads';     // .../plugins/intranet/uploads
$publicBase   = $CFG_GLPI['root_doc'] . '/plugins/intranet/uploads';

// Garante a pasta
if (!is_dir($destDirAbs)) { @mkdir($destDirAbs, 0775, true); }

/* ============================================================
   POST (upload do banner) — EXATAMENTE como no config
   - POST só salva arquivo em disco
   - Depois REDIRECT GET com ?banner=... (e mantém ?id=...)
   - NENHUMA gravação em banco via POST
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {

   $msg = '';
   if (isset($_FILES['arquivo']['name']) && $_FILES['arquivo']['error'] == 0) {

      $arquivo_tmp = $_FILES['arquivo']['tmp_name'];
      $nome        = $_FILES['arquivo']['name'];

      $extensao = strrchr($nome, '.');
      $extensao = strtolower($extensao);

      if (strstr('.jpg;.jpeg', $extensao)) {

         if (!is_writable($destDirAbs)) {
            $msg = 'Erro: pasta de destino não é gravável: ' . Html::entities_deep($destDirAbs);
         } else {
            // NÃO remove anterior (variadas notícias) – gera nome único
            $basename  = 'news_' . date('Ymd_His') . '_' . mt_rand(1000,9999) . '.jpg';
            $destFile  = $destDirAbs . '/' . $basename;

            if (@move_uploaded_file($arquivo_tmp, $destFile)) {
               @chmod($destFile, 0644);

               // cache-busting
               $urlWithV = $publicBase . '/' . $basename . '?v=' . time();

               // Redireciona para persistir no banco via GET (quando o usuário clicar em Salvar)
               // >>> MANTÉM o id na URL para não “zerar” os campos
               $qs = 'banner=' . urlencode($urlWithV);
               if ($editId > 0) {
                  $redirect = $CFG_GLPI['root_doc']
                           . '/plugins/intranet/front/news.form.php'
                           . '?id='.(int)$editId.'&'.$qs;
               } else {
                  $redirect = $CFG_GLPI['root_doc']
                           . '/plugins/intranet/front/news.form.php'
                           . '?'.$qs;
               }

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

   // se chegou aqui, houve erro de upload -> mostra mensagem e recarrega (mantendo id)
   Session::addMessageAfterRedirect('❌ '.$msg, false, ERROR);
   $back = $CFG_GLPI['root_doc'].'/plugins/intranet/front/news.form.php'.($editId>0?('?id='.$editId):'');
   Html::redirect($back);
   exit;
}

/* ============================================================
   GET (SALVAR) — via GET com atualização parcial (igual padrão)
   + category_id incluído
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['salvar'])) {
   // >>> incluímos category_id
   $fields = ['title','content','date_publication','date_expiration','banner','category_id'];
   $isAjax = isset($_GET['ajax']) && (int)$_GET['ajax'] === 1;

   $json = function(array $arr, int $status=200) {
      http_response_code($status);
      header('Content-Type: application/json; charset=UTF-8');
      echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      exit;
   };

   try {
      if (isset($_GET['add']) && (int)$_GET['add'] === 1) {
         $d=[]; foreach ($fields as $f) if (array_key_exists($f,$_GET)) { $v=trim($_GET[$f]??''); $d[$f]=($v===''?null:$v); }
         if (empty($d['title'])) {
            if ($isAjax) { $json(['ok'=>0,'msg'=>'Título é obrigatório.'], 400); }
            Session::addMessageAfterRedirect(__('Título é obrigatório.','intranet'), false, ERROR);
         } else {
            $uid=(int)Session::getLoginUserID();
            if ($uid<=0) {
               if ($isAjax) { $json(['ok'=>0,'msg'=>'Usuário inválido.'], 403); }
               Session::addMessageAfterRedirect(__('Usuário inválido.','intranet'), false, ERROR);
            } else {
               if (empty($d['date_publication'])) $d['date_publication']=date('Y-m-d H:i:s');
               $ok = $DB->insert('glpi_intranet_news', [
                  'users_id'         => $uid,
                  'category_id'      => isset($d['category_id']) && $d['category_id']!==null ? (int)$d['category_id'] : null,
                  'title'            => $d['title'] ?? null,
                  'content'          => $d['content'] ?? null,
                  'banner'           => $d['banner'] ?? null,
                  'date_publication' => $d['date_publication'] ?? null,
                  'date_expiration'  => $d['date_expiration'] ?? null,
                  'date_creation'    => date('Y-m-d H:i:s')
               ]);
               if ($ok) {
                  Session::addMessageAfterRedirect(__('Notícia salva.','intranet'), false, INFO);
               }
               if ($isAjax) { $json(['ok'=>$ok?1:0, 'msg'=>$ok?'Notícia salva.':'Erro ao criar.'], $ok?200:500); }
            }
         }

      } elseif (isset($_GET['update']) && (int)$_GET['update'] === 1) {
         $id=(int)($_GET['id']??0);
         if ($id<=0) {
            if ($isAjax) { $json(['ok'=>0,'msg'=>'ID inválido para atualização.'], 400); }
            Session::addMessageAfterRedirect(__('ID inválido para atualização.','intranet'), false, ERROR);
         } else {
            // Atualiza somente os campos PRESENTES na querystring (não zera os demais)
            $qsRaw = (string)($_SERVER['QUERY_STRING'] ?? '');
            $qsArr = []; parse_str($qsRaw, $qsArr);
            $allowed = array_flip($fields);
            $present = array_intersect_key($qsArr, $allowed);

            $up = [];
            foreach ($present as $key => $val) {
               $v = is_string($val) ? trim($val) : $val;
               if ($key === 'category_id') {
                  $up[$key] = ($v === '' ? null : (int)$v);
               } else {
                  $up[$key] = ($v === '' ? null : $v);
               }
            }

            if (empty($up)) {
               if ($isAjax) { $json(['ok'=>1,'msg'=>'Nada para salvar.'], 200); }
               Session::addMessageAfterRedirect(__('Nada para salvar.','intranet'), false, INFO);
            } else {
               $ok = $DB->update('glpi_intranet_news', $up, ['id'=>$id]);
               if ($ok) {
                  Session::addMessageAfterRedirect(__('Notícia salva.','intranet'), false, INFO);
               }
               if ($isAjax) { $json(['ok'=>$ok?1:0, 'msg'=>$ok?'Notícia salva.':'Erro ao atualizar.'], $ok?200:500); }
            }
         }
      } else {
         if ($isAjax) { $json(['ok'=>0,'msg'=>'Ação inválida.'], 400); }
         Session::addMessageAfterRedirect(__('Ação inválida.','intranet'), false, ERROR);
      }
   } catch (Throwable $e) {
      if ($isAjax) { $json(['ok'=>0,'msg'=>$e->getMessage()], 500); }
      Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
   }

   Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/news.form.php'.($editId>0?('?id='.$editId):''));
   exit;
}

/* ============================================================
   CARREGAMENTO (edição) + categorias
============================================================ */
$item = [
  'id'               => 0,
  'title'            => '',
  'content'          => '',
  'banner'           => '',
  'date_publication' => '',
  'date_expiration'  => '',
  'category_id'      => null
];

if ($editId > 0) {
  try {
    $res = $DB->request([
      'FROM'  => 'glpi_intranet_news',
      'WHERE' => ['id'=>$editId],
      'LIMIT' => 1
    ]);
    foreach ($res as $r) { $item = array_merge($item, $r); break; }
  } catch (Throwable $e) {
    Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
  }
}

// Carrega categorias (robusto)
$categories = [];
try {
   $sql = "SELECT id, name
           FROM glpi_intranet_news_categories
           ORDER BY name ASC";
   foreach ($DB->request($sql) as $c) {
      $categories[] = ['id' => (int)$c['id'], 'name' => (string)$c['name']];
   }
} catch (Throwable $e) {
   Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
}

// Banner para preview: prioriza ?banner= (upload), senão o do banco
$banner_pre = isset($_GET['banner']) ? trim($_GET['banner']) : trim($item['banner'] ?? '');

/* ============================================================
   UI
============================================================ */
Html::header($editId>0?__('Editar Notícia','intranet'):__('Nova Notícia','intranet'),
             $_SERVER['PHP_SELF'], 'helpdesk', 'PluginIntranetMenu');

$cssAbs = __DIR__.'/../assets/intranet.css';
echo '<link rel="stylesheet" href="'.$CFG_GLPI['root_doc'].'/plugins/intranet/assets/intranet.css?v='.(filemtime($cssAbs)?:time()).'">';

?>
<style>
  .intra-form .form-row { margin-bottom:14px; }
  .intra-form label { display:block; font-weight:600; margin-bottom:6px; }
  .intra-form input[type="text"],
  .intra-form input[type="datetime-local"],
  .intra-form input[type="file"],
  .intra-form select,
  .intra-form textarea { width:100%; padding:8px; }
  .intra-form .hint { font-size:12px; color:#666; margin-top:6px; display:block; }
  .intra-form .actions { display:flex; gap:10px; justify-content:flex-start; margin-top:8px; }
  /* linha com 3 colunas: Pub / Exp / Categoria */
  .row-3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; }
  @media (max-width: 900px) { .row-3 { grid-template-columns:1fr; } }
</style>
<?php

$quillBase = $CFG_GLPI['root_doc'].'/plugins/intranet/assets/lib/quill';
$quillAbs  = __DIR__.'/../assets/lib/quill';
if (is_file($quillAbs.'/quill.core.css')) echo '<link rel="stylesheet" href="'.$quillBase.'/quill.core.css?v='.(filemtime($quillAbs.'/quill.core.css')?:time()).'">';
if (is_file($quillAbs.'/quill.snow.css')) echo '<link rel="stylesheet" href="'.$quillBase.'/quill.snow.css?v='.(filemtime($quillAbs.'/quill.snow.css')?:time()).'">';

echo '<div id="intranet-root" class="intranet">';
Html::displayMessageAfterRedirect();
?>

<div class="intranet-grid" style="max-width:900px;margin:20px auto;">
  <div class="intra-left" style="flex:1 1 100%;">

    <div class="box">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="margin:0;"><?php echo $editId>0 ? '✏️ Editar Notícia' : '🆕 Nova Notícia'; ?></h3>
        <a class="btn secondary" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news_manager.php?sec=noticias&tab=todas">← Voltar para o Manager</a>
      </div>

      <div class="intra-form">

        <!-- Upload Banner (POST -> salva -> redirect GET mantendo id) -->
        <form method="post"
              action="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news.form.php<?php echo $editId>0?('?id='.(int)$editId):''; ?>"
              enctype="multipart/form-data"
              style="margin-bottom:16px;">
          <div class="form-row">
            <label for="arquivo">Banner (JPG/JPEG)</label>
            <input id="arquivo" type="file" name="arquivo" accept=".jpg,.jpeg" required>
            <span class="hint">Após o upload, a URL é pré-carregada; só grava no banco quando clicar em Salvar.</span>
          </div>
          <div class="actions">
            <button type="submit" class="btn">Enviar banner</button>
          </div>
        </form>

        <?php if (!empty($banner_pre)): ?>
          <div class="form-row">
            <label>Banner</label>
            <img src="<?php echo Html::cleanInputText($banner_pre); ?>" alt="Banner"
                 style="max-width:100%;height:auto;border:1px solid #ddd;border-radius:6px;">
          </div>
        <?php endif; ?>

        <!-- Form ADD/UPDATE (AJAX GET) -->
        <form id="formNews" method="get"
              action="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news.form.php">
          <input type="hidden" name="salvar" value="1">
          <input type="hidden" name="<?php echo $editId>0?'update':'add'; ?>" value="1">
          <input type="hidden" name="ajax" value="1">
          <?php if ($editId>0): ?><input type="hidden" name="id" value="<?php echo (int)$editId; ?>"><?php endif; ?>
          <?php if (!empty($banner_pre)): ?><input type="hidden" name="banner" value="<?php echo Html::cleanInputText($banner_pre); ?>"><?php endif; ?>

          <div id="msgArea" class="small" style="margin-bottom:8px;color:#2e7d32;display:none;"></div>

          <div class="form-row">
            <label for="title">Título*</label>
            <input id="title" type="text" name="title" required
                   value="<?php echo Html::cleanInputText($item['title'] ?? ''); ?>">
          </div>

          <!-- Publicação / Expiração / Categoria em linha -->
          <div class="row-3">
            <div class="form-row">
              <label for="date_publication">Publicação</label>
              <input id="date_publication" type="datetime-local" name="date_publication"
                     value="<?php echo !empty($item['date_publication']) ? Html::cleanInputText(date('Y-m-d\\TH:i', strtotime($item['date_publication']))) : ''; ?>">
            </div>

            <div class="form-row">
              <label for="date_expiration">Expiração</label>
              <input id="date_expiration" type="datetime-local" name="date_expiration"
                     value="<?php echo !empty($item['date_expiration']) ? Html::cleanInputText(date('Y-m-d\\TH:i', strtotime($item['date_expiration']))) : ''; ?>">
            </div>

            <div class="form-row">
              <label for="category_id">Categoria</label>
              <select id="category_id" name="category_id">
                <option value="">—</option>
                <?php
                  $currentCat = !empty($item['category_id']) ? (int)$item['category_id'] : 0;
                  foreach ($categories as $cat) {
                    $sel = ($cat['id'] === $currentCat) ? 'selected' : '';
                    echo '<option value="'.(int)$cat['id'].'" '.$sel.'>'
                          . Html::entities_deep($cat['name'])
                          .'</option>';
                  }
                ?>
              </select>
              <?php if (empty($categories)): ?>
                <div class="small" style="margin-top:6px;">
                  Nenhuma categoria encontrada. Cadastre em
                  <a href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news_manager.php?sec=noticias&tab=categorias">Categorias</a>.
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row">
            <label>Conteúdo</label>
            <textarea name="content" class="rte" rows="8" style="display:none;"><?php
              echo Html::cleanPostForTextArea($item['content'] ?? '');
            ?></textarea>
            <div class="rte-quill" style="height:260px;"></div>
          </div>

          <div class="actions">
            <button type="submit" class="vsubmit">💾 Salvar notícia</button>
            <a class="btn secondary" href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news.form.php">➕ Criar nova notícia</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>

<?php
echo '</div>'; // fecha #intranet-root

/* ============================================================
   JS — Quill e submissão AJAX (GET)
============================================================ */
$quillBase = $CFG_GLPI['root_doc'].'/plugins/intranet/assets/lib/quill';
$quillAbs  = __DIR__.'/../assets/lib/quill';
if (is_file($quillAbs.'/quill.js')) {
   echo '<script src="'.$quillBase.'/quill.js?v='.(filemtime($quillAbs.'/quill.js')?:time()).'"></script>';
} elseif (is_file($quillAbs.'/quill.core.js')) {
   echo '<script src="'.$quillBase.'/quill.core.js?v='.(filemtime($quillAbs.'/quill.core.js')?:time()).'"></script>';
}
$initAbs = __DIR__.'/../assets/js/pages/news-quill.init.js';
$initUrl = $CFG_GLPI['root_doc'].'/plugins/intranet/assets/js/pages/news-quill.init.js';
echo is_file($initAbs)
   ? '<script src="'.$initUrl.'?v='.(filemtime($initAbs)?:time()).'"></script>'
   : '<script>console.warn("Falta: plugins/intranet/assets/js/pages/news-quill.init.js");</script>';
?>

<script>
// SUBMISSÃO AJAX (GET) — sem refresh (com reload rápido para mostrar a barra padrão)
(function(){
  var form = document.getElementById('formNews');
  if (!form) return;

  function syncQuillToTextarea() {
    var ta  = form.querySelector('textarea[name="content"]');
    var ed  = document.querySelector('.rte-quill .ql-editor');
    if (ta && ed) ta.value = ed.innerHTML;
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    syncQuillToTextarea();

    var params = new URLSearchParams(new FormData(form));
    var url = form.action + (form.action.indexOf('?')>=0 ? '&' : '?') + params.toString();

    var btn = form.querySelector('button[type="submit"]');
    var msg = document.getElementById('msgArea');
    if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }
    if (msg) { msg.style.display='none'; msg.textContent=''; msg.style.color='#2e7d32'; }

    fetch(url, { method: 'GET', credentials: 'same-origin' })
      .then(r => r.json())
      .then(j => {
        if (j && j.ok) {
          if (msg) {
            msg.textContent = j.msg || 'Notícia salva.';
            msg.style.display='block';
            msg.style.color='#2e7d32';
          }
          // Recarrega a mesma página para exibir a barra padrão (Session::addMessageAfterRedirect)
          setTimeout(function(){
            window.location = "<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/news.form.php<?php echo ($editId>0)?('?id='.(int)$editId):''; ?>";
          }, 400);
        } else {
          if (msg) {
            msg.textContent = (j && j.msg) ? j.msg : 'Falha ao salvar.';
            msg.style.display='block';
            msg.style.color='#c62828';
          }
        }
      })
      .catch(() => {
        if (msg) { msg.textContent = 'Erro de comunicação.'; msg.style.display='block'; msg.style.color='#c62828'; }
      })
      .finally(() => {
        if (btn) { btn.disabled = false; btn.textContent = '💾 Salvar notícia'; }
      });
  });
})();
</script>

<?php Html::footer();
