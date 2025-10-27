<?php
// plugins/intranet/front/news_manager.php
include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

global $CFG_GLPI, $DB;

/* ================= Helpers ================= */
function intranet_redirect_self(array $qs = []) {
  global $CFG_GLPI;
  $base = $CFG_GLPI['root_doc'].'/plugins/intranet/front/news_manager.php';
  if (!empty($qs)) { $base .= '?' . http_build_query($qs); }
  Html::redirect($base); exit;
}
function qv($k, $d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

/* ============ AÇÕES via GET (Notícias, Categorias & RSS) ============ */
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['salvar'])) {
  try {
    /* ------- Notícias: Excluir direto ------- */
    if (qv('sec')==='noticias') {
      if ((int)qv('delete')===1) {
        $id = (int)qv('id',0);
        $ok = ($id>0) ? $DB->delete('glpi_intranet_news', ['id'=>$id]) : false;
        Session::addMessageAfterRedirect($ok?'Notícia excluída.':'ID inválido.', false, $ok?INFO:ERROR);
        intranet_redirect_self(['sec'=>'noticias','tab'=> qv('tab','todas')]);
      }
    }

    /* ------- Categorias ------- */
    if (qv('sec')==='noticias' && qv('tab')==='categorias') {

      // Criar
      if ((int)qv('add')===1) {
        $name = qv('name');
        $ok = ($name!=='') ? $DB->insert('glpi_intranet_news_categories', [
          'name'=>$name, 'date_creation'=>date('Y-m-d H:i:s')
        ]) : false;
        Session::addMessageAfterRedirect($ok?'Categoria criada.':'Nome é obrigatório.', false, $ok?INFO:ERROR);
        intranet_redirect_self(['sec'=>'noticias','tab'=>'categorias']);
      }

      // Atualizar
      if ((int)qv('update')===1) {
        $id=(int)qv('id',0); $name=qv('name');
        $ok = ($id>0 && $name!=='') ? $DB->update('glpi_intranet_news_categories',['name'=>$name],['id'=>$id]) : false;
        Session::addMessageAfterRedirect($ok?'Categoria atualizada.':'Dados inválidos.', false, $ok?INFO:ERROR);
        intranet_redirect_self(['sec'=>'noticias','tab'=>'categorias']);
      }

      // Excluir
      if ((int)qv('delete')===1) {
        $id=(int)qv('id',0);
        if ($id>0) { $DB->update('glpi_intranet_news',['category_id'=>null],['category_id'=>$id]); }
        $ok = ($id>0) ? $DB->delete('glpi_intranet_news_categories',['id'=>$id]) : false;
        Session::addMessageAfterRedirect($ok?'Categoria excluída.':'ID inválido.', false, $ok?INFO:ERROR);
        intranet_redirect_self(['sec'=>'noticias','tab'=>'categorias']);
      }
    }

    /* ------- RSS ------- */
    if (qv('sec')==='rss') {
      // Une $_GET + QUERY_STRING (robusto contra filtros do GLPI)
      $qs_arr = [];
      if (!empty($_SERVER['QUERY_STRING'])) { parse_str($_SERVER['QUERY_STRING'], $qs_arr); }
      $g = array_merge($qs_arr, $_GET);

      $GET_RSS = function($key_new, $key_old = null, $default = '') use ($g) {
        if ($key_old === null) { $key_old = $key_new; }
        if (array_key_exists($key_new, $g)) return trim((string)$g[$key_new]);
        if (array_key_exists($key_old, $g)) return trim((string)$g[$key_old]);
        return $default;
      };

      // Criar
      if ((int)qv('add')===1) {
        $name   = $GET_RSS('rss_name', 'name');
        $feed   = $GET_RSS('rss_feed_url', 'feed_url');
        $tag    = $GET_RSS('rss_site_tag', 'site_tag', '');
        $status = $GET_RSS('rss_status', 'status', 'ativo');
        $catRaw = $GET_RSS('rss_id_categories', 'id_categories', '');
        $catId  = ($catRaw !== '' ? (int)$catRaw : null);

        if ($name === '' || $feed === '') {
          Session::addMessageAfterRedirect('Nome e Feed URL são obrigatórios.', false, ERROR);
          intranet_redirect_self(['sec'=>'rss']);
        }

        $data = [
          'name'          => $name,
          'site_tag'      => $tag,
          'feed_url'      => $feed,
          'status'        => ($status==='inativo'?'inativo':'ativo'),
          'id_categories' => $catId
        ];

        $ok = $DB->insert('glpi_intranet_rss_sources', $data);

        if ($ok) {
          Session::addMessageAfterRedirect('Fonte RSS criada.', false, INFO);
        } else {
          $err = method_exists($DB,'error') ? $DB->error() : 'Erro desconhecido no banco.';
          Session::addMessageAfterRedirect('❌ Erro ao salvar: '.$err, false, ERROR);
        }
        intranet_redirect_self(['sec'=>'rss']);
      }

      // Atualizar
      if ((int)qv('update')===1) {
        $id     = (int)qv('id',0);
        $name   = $GET_RSS('rss_name', 'name');
        $feed   = $GET_RSS('rss_feed_url', 'feed_url');
        $tag    = $GET_RSS('rss_site_tag', 'site_tag', '');
        $status = $GET_RSS('rss_status', 'status', 'ativo');
        $catRaw = $GET_RSS('rss_id_categories', 'id_categories', '');
        $catId  = ($catRaw !== '' ? (int)$catRaw : null);

        if (!($id>0 && $name!=='' && $feed!=='')) {
          Session::addMessageAfterRedirect('Dados inválidos.', false, ERROR);
          intranet_redirect_self(['sec'=>'rss']);
        }

        $up = [
          'name'          => $name,
          'feed_url'      => $feed,
          'site_tag'      => $tag,
          'status'        => ($status==='inativo'?'inativo':'ativo'),
          'id_categories' => $catId
        ];

        $ok = $DB->update('glpi_intranet_rss_sources',$up,['id'=>$id]);

        if ($ok) {
          Session::addMessageAfterRedirect('Fonte RSS atualizada.', false, INFO);
        } else {
          $err = method_exists($DB,'error') ? $DB->error() : 'Erro desconhecido no banco.';
          Session::addMessageAfterRedirect('❌ Erro ao atualizar: '.$err, false, ERROR);
        }
        intranet_redirect_self(['sec'=>'rss']);
      }

      // Excluir
      if ((int)qv('delete')===1) {
        $id = (int)qv('id',0);
        $ok = $id>0 ? $DB->delete('glpi_intranet_rss_sources', ['id'=>$id]) : false;
        Session::addMessageAfterRedirect($ok ? 'Fonte RSS excluída.' : 'ID inválido.', false, $ok?INFO:ERROR);
        intranet_redirect_self(['sec'=>'rss']);
      }
    }

  } catch (Throwable $e) {
    Session::addMessageAfterRedirect('❌ '.$e->getMessage(), false, ERROR);
    intranet_redirect_self(['sec'=>qv('sec','noticias'),'tab'=>qv('tab','todas')]);
  }
}

/* ================= Dados ================= */
$cats=[]; try {
  foreach ($DB->request(['FROM'=>'glpi_intranet_news_categories','ORDER'=>'name ASC']) as $r) $cats[]=$r;
} catch(Throwable $e){}

$news=[]; try {
  $sql="SELECT n.*, c.name AS cat_name
        FROM glpi_intranet_news n
        LEFT JOIN glpi_intranet_news_categories c ON c.id=n.category_id
        ORDER BY (n.date_publication IS NULL) ASC, n.date_publication DESC, n.id DESC
        LIMIT 500";
  foreach ($DB->request($sql) as $r) $news[]=$r;
} catch(Throwable $e){}

$rss=[]; try {
  foreach ($DB->request(['FROM'=>'glpi_intranet_rss_sources','ORDER'=>['status DESC','name ASC']]) as $r) $rss[]=$r;
} catch(Throwable $e){}

/* ================= UI ================= */
Html::header(__('Gestor de Notícias','intranet'), $_SERVER['PHP_SELF'], 'tools', 'intranet');

$root   = $CFG_GLPI['root_doc'].'/plugins/intranet/front';
$assets = $CFG_GLPI['root_doc'].'/plugins/intranet/assets';
$cssAbs = __DIR__.'/../assets/intranet.css';
echo '<link rel="stylesheet" href="'.$assets.'/intranet.css?v='.(filemtime($cssAbs)?:time()).'">';

echo '<div class="intranet intranet-manager">'; // escopo duplo (não vaza CSS)
Html::displayMessageAfterRedirect();
?>
<style>
  .intranet-manager .im-layout{display:flex;gap:18px;max-width:1200px;margin:18px auto;}
  .intranet-manager .im-sidebar{width:220px;background:transparent;}
  .intranet-manager .im-vtab{display:block;padding:10px 12px;margin-bottom:8px;border:1px solid #cfd8ea;border-radius:8px;background:#f4f7ff;text-decoration:none;color:#1a2b4c;}
  .intranet-manager .im-vtab.active{background:#fff;font-weight:600;}
  .intranet-manager .im-content{flex:1;}
  .intranet-manager .im-htabs{display:flex;gap:6px;margin-bottom:12px;}
  .intranet-manager .im-htabs a{padding:8px 12px;border:1px solid #cfd8ea;border-radius:8px;background:#f4f7ff;text-decoration:none;color:#1a2b4c;}
  .intranet-manager .im-htabs a.active{background:#fff;font-weight:600;}
  .intranet-manager .im-box{background:#fff;border:1px solid #cfd8ea;border-radius:12px;padding:16px;}
  .intranet-manager .im-inline-card{border:1px solid #cfd8ea;border-radius:10px;padding:12px;background:#f9fbff;}
  .intranet-manager .im-inline-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
  .intranet-manager .im-inline-row label{display:inline-block;}
  .intranet-manager .im-inline-row .grow{flex:1;}
  .intranet-manager .im-table{width:100%;border-collapse:collapse;}
  .intranet-manager .im-table th,.intranet-manager .im-table td{border-bottom:1px solid #eee;padding:10px;vertical-align:middle;}
  .intranet-manager .im-table .actions{white-space:nowrap;}
  .intranet-manager .im-table .actions .btn{display:inline-block;margin-right:8px;}
  .intranet-manager .im-info{margin-bottom:10px;line-height:1.5;}
</style>
<?php
$sec = qv('sec','noticias'); // noticias | rss
$tab = qv('tab','todas');    // todas | criar | categorias
?>

<div class="im-layout">
  <div class="im-sidebar">
    <a class="im-vtab <?php echo ($sec==='noticias'?'active':''); ?>" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=<?php echo urlencode($tab); ?>">📰 Notícias</a>
    <a class="im-vtab <?php echo ($sec==='rss'?'active':''); ?>" href="<?php echo $root; ?>/news_manager.php?sec=rss">🧩 RSS</a>
  </div>

  <div class="im-content">
    <?php if ($sec === 'noticias'): ?>

      <div class="im-htabs">
        <a class="<?php echo ($tab==='todas'?'active':''); ?>" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=todas">Todas as Notícias</a>
        <a class="<?php echo ($tab==='criar'?'active':''); ?>" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=criar">Criar Notícia</a>
        <a class="<?php echo ($tab==='categorias'?'active':''); ?>" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=categorias">Categorias</a>
      </div>

      <?php if ($tab === 'todas'): ?>
        <div class="im-box">
          <div class="im-inline-card" style="margin:0 0 12px;">
            <a class="btn secondary" href="<?php echo $root; ?>/news.php" target="_blank">👁️ Ver listagem pública</a>
          </div>

          <?php if (empty($news)): ?>
            <div class="small">Nenhuma notícia cadastrada.</div>
          <?php else: ?>
            <table class="im-table">
              <thead>
                <tr>
                  <th style="width:55%;">Título</th>
                  <th>Categoria</th>
                  <th>Publicação</th>
                  <th style="width:250px;">Ações</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($news as $n): ?>
                <tr>
                  <td><?php echo Html::entities_deep($n['title']); ?></td>
                  <td><?php echo Html::entities_deep($n['cat_name'] ?: '—'); ?></td>
                  <td><?php echo !empty($n['date_publication']) ? Html::entities_deep(date('d/m/Y H:i', strtotime($n['date_publication']))) : '—'; ?></td>
                  <td class="actions">
                    <a class="btn" href="<?php echo $root; ?>/news.form.php?id=<?php echo (int)$n['id']; ?>">✏️ Editar</a>
                    <!-- EXCLUIR direto no manager -->
                    <a class="btn" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=todas&salvar=1&delete=1&id=<?php echo (int)$n['id']; ?>" onclick="return confirm('Excluir esta notícia?');">🗑️ Excluir</a>
                    <a class="btn" href="<?php echo $root; ?>/news.view.php?id=<?php echo (int)$n['id']; ?>" target="_blank">👁️ Ver</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'criar'): ?>
        <div class="im-box">
          <h3 style="margin:0 0 10px;">🆕 Criar Notícia</h3>
          <div class="im-inline-card">
            <p class="im-info">
              Abrir o formulário completo para criar a notícia.
            </p>
            <a class="vsubmit" href="<?php echo $root; ?>/news.form.php">➕ Abrir formulário de criação</a>
          </div>
        </div>

      <?php elseif ($tab === 'categorias'): ?>
        <div class="im-box">
          <h3 style="margin:0 0 12px;">🏷️ Categorias</h3>

          <!-- Criar (uma linha) -->
          <div class="im-inline-card" style="margin-bottom:14px;">
            <form method="get" action="<?php echo $root; ?>/news_manager.php" class="im-inline-row">
              <input type="hidden" name="sec" value="noticias">
              <input type="hidden" name="tab" value="categorias">
              <input type="hidden" name="salvar" value="1">
              <input type="hidden" name="add" value="1">
              <label for="catname" style="min-width:70px;">Nome</label>
              <input id="catname" class="grow" type="text" name="name" required>
              <button type="submit" class="btn">➕ Criar categoria</button>
            </form>
          </div>

          <!-- Lista (uma linha/item) -->
          <?php if (empty($cats)): ?>
            <div class="small">Nenhuma categoria cadastrada.</div>
          <?php else: ?>
            <table class="im-table">
              <thead><tr><th>Nome</th><th style="width:220px;">Ações</th></tr></thead>
              <tbody>
              <?php foreach ($cats as $c): ?>
                <tr>
                  <td>
                    <form method="get" action="<?php echo $root; ?>/news_manager.php" class="im-inline-row">
                      <input type="hidden" name="sec" value="noticias">
                      <input type="hidden" name="tab" value="categorias">
                      <input type="hidden" name="salvar" value="1">
                      <input type="hidden" name="update" value="1">
                      <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                      <input type="text" name="name" value="<?php echo Html::cleanInputText($c['name']); ?>" class="grow">
                      <button type="submit" class="btn">💾 Salvar</button>
                    </form>
                  </td>
                  <td class="actions">
                    <a class="btn" href="<?php echo $root; ?>/news_manager.php?sec=noticias&tab=categorias&salvar=1&delete=1&id=<?php echo (int)$c['id']; ?>" onclick="return confirm('Excluir esta categoria? Notícias ligadas a ela ficarão sem categoria.');">🗑️ Excluir</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($sec === 'rss'): ?>

      <div class="im-box">
        <h3 style="margin:0 0 12px;">🧩 Fontes RSS</h3>

        <!-- Form (GET) -->
        <div class="im-inline-card" style="margin-bottom:6px;">
          <form method="get" action="<?php echo $root; ?>/news_manager.php">
            <input type="hidden" name="sec" value="rss">
            <input type="hidden" name="salvar" value="1">
            <input type="hidden" name="add" value="1">

            <!-- Linha 1 -->
            <div class="im-inline-row">
              <label for="rssname" style="min-width:60px;">Nome</label>
              <input id="rssname" class="grow" type="text" name="rss_name" required>

              <label for="rsstag" style="min-width:70px;">Tag/Site</label>
              <input id="rsstag" class="grow" type="text" name="rss_site_tag" placeholder="Opcional">

              <label for="rssurl" style="min-width:70px;">Feed URL</label>
              <input id="rssurl" class="grow" type="url" name="rss_feed_url" required>
            </div>

            <!-- Linha 2 -->
            <div class="im-inline-row" style="margin-top:10px;">
              <label for="rsscat" style="min-width:80px;">Categoria</label>
              <select id="rsscat" name="rss_id_categories" class="grow">
                <option value="">—</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>"><?php echo Html::entities_deep($c['name']); ?></option>
                <?php endforeach; ?>
              </select>

              <label for="rssstatus" style="min-width:60px;">Status</label>
              <select id="rssstatus" name="rss_status" class="grow">
                <option value="ativo">ativo</option>
                <option value="inativo">inativo</option>
              </select>

              <button type="submit" class="btn">➕ Adicionar fonte</button>
            </div>
          </form>
        </div>

        <!-- Lista (somente campos editáveis nas colunas) -->
        <?php if (empty($rss)): ?>
          <div class="small">Nenhuma fonte cadastrada.</div>
        <?php else: ?>
          <table class="im-table">
            <thead>
              <tr>
                <th style="width:22%;">Nome</th>
                <th style="width:12%;">Tag</th>
                <th style="width:30%;">Feed</th>
                <th style="width:14%;">Categoria</th>
                <th style="width:8%;">Status</th>
                <th style="width:10%;">Última busca</th>
                <th style="width:14%;">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rss as $r): ?>
              <tr>
                <form method="get" action="<?php echo $root; ?>/news_manager.php">
                  <input type="hidden" name="sec" value="rss">
                  <input type="hidden" name="salvar" value="1">
                  <input type="hidden" name="update" value="1">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">

                  <td>
                    <input type="text" name="rss_name" value="<?php echo Html::cleanInputText($r['name']); ?>" style="width:100%;">
                  </td>
                  <td>
                    <input type="text" name="rss_site_tag" value="<?php echo Html::cleanInputText($r['site_tag']); ?>" style="width:100%;">
                  </td>
                  <td>
                    <input type="url" name="rss_feed_url" value="<?php echo Html::cleanInputText($r['feed_url']); ?>" style="width:100%;">
                  </td>
                  <td>
                    <select name="rss_id_categories" style="width:100%;">
                      <option value="">—</option>
                      <?php foreach ($cats as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$r['id_categories']===(int)$c['id']?'selected':''); ?>>
                          <?php echo Html::entities_deep($c['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="rss_status" style="width:100%;">
                      <option value="ativo"   <?php echo ($r['status']==='ativo'?'selected':''); ?>>ativo</option>
                      <option value="inativo" <?php echo ($r['status']==='inativo'?'selected':''); ?>>inativo</option>
                    </select>
                  </td>
                  <td><?php echo !empty($r['last_fetch']) ? Html::entities_deep(date('d/m/Y H:i', strtotime($r['last_fetch']))) : '—'; ?></td>
                  <td class="actions">
                    <button type="submit" class="btn">💾 Salvar</button>
                    <a class="btn" href="<?php echo $root; ?>/news_manager.php?sec=rss&salvar=1&delete=1&id=<?php echo (int)$r['id']; ?>" onclick="return confirm('Excluir esta fonte RSS?');">🗑️ Excluir</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php
echo '</div>'; // fecha .intranet .intranet-manager
Html::footer();
