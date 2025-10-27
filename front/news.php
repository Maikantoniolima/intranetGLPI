<?php
// plugins/intranet/front/news.php
include('../../../inc/includes.php');

global $CFG_GLPI, $DB;

$perpage = 10;
$page    = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset  = ($page - 1) * $perpage;

/* === total === */
$total = 0;
try {
   foreach ($DB->request("SELECT COUNT(*) AS cnt FROM glpi_intranet_news") as $r) {
      $total = (int)$r['cnt']; break;
   }
} catch (Throwable $e) {}

$maxpages = max(1, (int)ceil($total / $perpage));
if ($page > $maxpages) {
   Html::redirect($CFG_GLPI['root_doc'].'/plugins/intranet/front/news.php?p='.$maxpages);
   exit;
}

/* === dados (inclui content para gerar o resumo) === */
$rows = [];
try {
   $sql = "
      SELECT n.id, n.title, n.banner, n.content, n.date_publication, c.name AS cat_name
      FROM glpi_intranet_news n
      LEFT JOIN glpi_intranet_news_categories c ON c.id = n.category_id
      ORDER BY (n.date_publication IS NULL) ASC, n.date_publication DESC, n.id DESC
      LIMIT ".(int)$perpage." OFFSET ".(int)$offset;
   foreach ($DB->request($sql) as $r) { $rows[] = $r; }
} catch (Throwable $e) {}

Html::header(__('Notícias','intranet'), $_SERVER['PHP_SELF'], 'helpdesk', 'PluginIntranetMenu');

$assets = $CFG_GLPI['root_doc'].'/plugins/intranet/assets';
$cssAbs = __DIR__.'/../assets/intranet.css';
echo '<link rel="stylesheet" href="'.$assets.'/intranet.css?v='.(filemtime($cssAbs)?:time()).'">';

$placeholder = $CFG_GLPI['root_doc'].'/pics/warranty.png'; // imagem padrão
?>
<style>
.news-list { max-width: 1100px; margin: 18px auto; }
.news-item {
  display:flex; gap:14px; align-items:flex-start;
  background:#fff; border:1px solid #cfd8ea; border-radius:12px;
  padding:12px; margin-bottom:12px;
}
.news-thumb {
  width:110px; height:110px; border-radius:10px; object-fit:cover; flex:0 0 110px;
  background:#eef2ff; border:1px solid #e5e7eb;
}
.news-body { flex:1; min-width:0; }
.news-meta { font-size:12px; color:#6b7280; margin:0 0 4px; display:flex; gap:14px; flex-wrap:wrap; }
.news-title {
  font-size:22px; font-weight:700; color:#0b2a5a; margin:0 0 6px;
  overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
}
.news-excerpt {
  font-size:14px; color:#374151; margin:0 0 10px;
  overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
}
.news-actions { display:flex; gap:8px; }
.news-actions .btn {
  display:inline-block; background:#0b2a5a; color:#fff; text-decoration:none;
  padding:7px 11px; border-radius:8px; font-weight:600; line-height:1;
}
.news-actions .btn:hover { opacity:.92; }
.news-actions .btn.outline {
  background:#fff; color:#0b2a5a; border:2px solid #0b2a5a;
  padding:6px 10px;
}

/* paginação */
.pager { display:flex; gap:6px; justify-content:center; margin:16px 0; flex-wrap:wrap; }
.pager a, .pager span {
  display:inline-block; padding:8px 12px; border:1px solid #cfd8ea; border-radius:8px; text-decoration:none;
  background:#f4f7ff; color:#1a2b4c; min-width:38px; text-align:center; font-weight:600;
}
.pager .current { background:#fff; }
</style>

<div class="intranet news-list">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <h3 style="margin:0;">📰 Todas as notícias</h3>
    <a class="btn" style="background:#0b2a5a;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;"
       href="<?php echo $CFG_GLPI['root_doc']; ?>/plugins/intranet/front/dashboard.php">← Início</a>
  </div>

  <?php if (empty($rows)): ?>
    <div class="box">Nenhuma notícia encontrada.</div>
  <?php else: ?>
    <?php foreach ($rows as $n):
      $id    = (int)$n['id'];
      $title = (string)$n['title'];
      $cat   = !empty($n['cat_name']) ? $n['cat_name'] : '—';
      $pub   = !empty($n['date_publication']) ? date('d/m/Y H:i', strtotime($n['date_publication'])) : '—';
      $img   = !empty($n['banner']) ? $n['banner'] : $placeholder;
      $view  = $CFG_GLPI['root_doc'].'/plugins/intranet/front/news.view.php?id='.$id;

      // resumo: remove HTML, normaliza espaços e limita ~160 chars
      $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$n['content'])));
      if (function_exists('mb_strlen')) {
         if (mb_strlen($excerpt) > 160) $excerpt = mb_substr($excerpt, 0, 160).'…';
      } else {
         if (strlen($excerpt) > 160) $excerpt = substr($excerpt, 0, 160).'…';
      }
    ?>
      <div class="news-item">
        <img class="news-thumb" src="<?php echo Html::cleanInputText($img); ?>" alt="">
        <div class="news-body">
          <div class="news-meta">
            <div><strong>Publicada:</strong> <?php echo Html::entities_deep($pub); ?></div>
            <div><strong>Categoria:</strong> <?php echo Html::entities_deep($cat); ?></div>
          </div>
          <h2 class="news-title" title="<?php echo Html::cleanInputText($title); ?>">
            <?php echo Html::entities_deep($title); ?>
          </h2>
          <?php if ($excerpt !== ''): ?>
            <p class="news-excerpt"><?php echo Html::entities_deep($excerpt); ?></p>
          <?php endif; ?>
          <div class="news-actions">
            <a class="btn" href="<?php echo $view; ?>">👁️ Ver</a>
            <button class="btn outline share-btn" type="button"
                    data-link="<?php echo Html::cleanInputText($view); ?>">🔗 Compartilhar</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($maxpages > 1): ?>
      <div class="pager">
        <?php
          $root = $CFG_GLPI['root_doc'].'/plugins/intranet/front/news.php';
          $mk   = function($p,$label=null,$current=false) use($root){
            $label = $label ?? $p;
            if ($current) {
              echo '<span class="current">'.(int)$label.'</span>';
            } else {
              echo '<a href="'.$root.'?p='.(int)$p.'">'.Html::entities_deep($label).'</a>';
            }
          };
          if ($page > 1) $mk($page-1,'«');

          $start = max(1, $page-2);
          $end   = min($maxpages, $page+2);
          if ($start > 1) $mk(1);
          if ($start > 2) echo '<span>…</span>';
          for ($i=$start; $i<=$end; $i++) $mk($i, null, $i===$page);
          if ($end < $maxpages-1) echo '<span>…</span>';
          if ($end < $maxpages)   $mk($maxpages);

          if ($page < $maxpages) $mk($page+1,'»');
        ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
// Compartilhar (copia link)
document.querySelectorAll('.share-btn').forEach(function(btn){
  btn.addEventListener('click', async function(){
    var link = this.getAttribute('data-link') || '';
    if (!link) return;
    try {
      await navigator.clipboard.writeText(link);
      var old = this.textContent;
      this.textContent = '✅ Copiado!';
      setTimeout(()=>{ this.textContent = old; }, 1200);
    } catch(e) {
      var ta = document.createElement('textarea');
      ta.value = link; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      var old = this.textContent;
      this.textContent = '✅ Copiado!';
      setTimeout(()=>{ this.textContent = old; }, 1200);
    }
  });
});
</script>

<?php Html::footer();
