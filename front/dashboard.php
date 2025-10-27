<?php
// plugins/intranet/front/dashboard.php
include('../../../inc/includes.php');
Session::checkLoginUser();

global $CFG_GLPI, $DB;

// ===== NÃO SAIR NADA NA TELA ANTES DOS AJAX =====

// (Opcional) Ping de diagnóstico: /dashboard.php?ajax=ping
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ping') {
   header('Content-Type: application/json; charset=UTF-8');
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
   echo json_encode(['ok'=>true, 'ts'=>time()], JSON_UNESCAPED_UNICODE);
   exit;
}

// Carrega config (para clima)
$conf = PluginIntranetConfig::getConfig();

/* =========================
   Helpers (nome, avatar, data)
   ========================= */
function intranet_get_user_avatar_url(int $userid): string {
   global $CFG_GLPI;

   // 1) Melhor caminho: método oficial (se existir)
   if (class_exists('User')) {
      $u = new User();
      if ($u->getFromDB($userid)) {
         if (method_exists($u, 'getPictureUrl')) {
            $url = (string)$u->getPictureUrl();
            if (!empty($url)) return $url;
         }

         // 2) Campo picture vindo do banco
         $pic = trim((string)($u->fields['picture'] ?? ''));
         if ($pic !== '') {
            // http/https direto?
            if (preg_match('~^https?://~i', $pic)) {
               return $pic;
            }
            // Caminho interno do GLPI: _pictures/<path>
            $pic = ltrim($pic, '/');
            $min = preg_replace('/(\.\w+)$/', '_min$1', $pic); // gera *_min.png
            $base = rtrim($CFG_GLPI['root_doc'], '/') . '/front/document.send.php?file=_pictures/';

            // Preferir a miniatura; se não existir o GLPI retorna 404,
            // então no <img> vamos colocar um onerror pra cair no original.
            return $base . rawurlencode($min);
         }
      }
   }

   // 3) Fallback seguro do GLPI
   return rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/intranet/pics/pics.png';
}


function intranet_get_user_display_name(array $row, int $userid): string {
   $firstname = trim((string)($row['firstname'] ?? ''));
   $realname  = trim((string)($row['realname']  ?? ''));
   $login     = trim((string)($row['name']      ?? ''));
   $full = trim($firstname . ' ' . $realname);
   if ($full !== '') return $full;
   if ($login !== '') return $login;
   return 'Usuário #'.$userid;
}
function intranet_valid_date_ymd($s) {
   if (!is_string($s)) return false;
   if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
   [$y,$m,$d] = explode('-', $s);
   return checkdate((int)$m,(int)$d,(int)$y);
}

/* =========================
   AJAX: clima (GET)
   ========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'weather') {
   header('Content-Type: application/json; charset=UTF-8');
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
   @ini_set('display_errors', '0');

   $key = trim((string)($conf['weather_api_key'] ?? ''));
   $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
   $lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;

   if (!$key || is_null($lat) || is_null($lon)) {
      echo json_encode(['ok'=>false,'msg'=>'Parâmetros insuficientes'], JSON_UNESCAPED_UNICODE); exit;
   }

   $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid=".$key."&units=metric&lang=pt_br";
   $ch  = curl_init($url);
   curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 8,
      CURLOPT_SSL_VERIFYPEER => true
   ]);
   $resp = curl_exec($ch);
   $err  = curl_error($ch);
   curl_close($ch);

   if ($err || !$resp) {
      echo json_encode(['ok'=>false,'msg'=>'Falha ao consultar o clima'], JSON_UNESCAPED_UNICODE); exit;
   }

   $j = json_decode($resp, true);
   $city = $j['name'] ?? '';
   $temp = isset($j['main']['temp']) ? round((float)$j['main']['temp']) : null;

   echo json_encode(['ok'=>true, 'city'=>$city, 'temp'=>$temp], JSON_UNESCAPED_UNICODE); exit;
}

/* =========================
   AJAX: salvar birthday (GET + salvar=1)
   ========================= */
if (
   isset($_GET['ajax']) && $_GET['ajax'] === 'birthday_save' &&
   isset($_GET['salvar']) && $_GET['salvar'] === '1'
) {
   header('Content-Type: application/json; charset=UTF-8');
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
   @ini_set('display_errors', '0');

   $uid = (int)Session::getLoginUserID();
   if ($uid <= 0) { echo json_encode(['ok'=>false, 'msg'=>'Sessão inválida.']); exit; }

   if (!$DB->fieldExists('glpi_users','birthday')) {
      echo json_encode(['ok'=>false, 'msg'=>'Campo de aniversário indisponível.']); exit;
   }

   $date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
   if (!intranet_valid_date_ymd($date)) {
      echo json_encode(['ok'=>false, 'msg'=>'Data inválida. Use o seletor.']); exit;
   }

   $date_sql = $DB->escape($date);
   $ok = $DB->query("UPDATE glpi_users SET birthday = '{$date_sql}' WHERE id = {$uid} LIMIT 1");
   echo json_encode(['ok'=>(bool)$ok]); exit;
}

// ===== A PARTIR DAQUI PODE RENDERIZAR A PÁGINA =====

Html::header(__('Intranet','intranet'), $_SERVER['PHP_SELF'], 'helpdesk', 'intranet');

echo '<link rel="stylesheet" href="'.$CFG_GLPI['root_doc'].'/plugins/intranet/assets/intranet.css">';

// SCOPE
echo '<div id="intranet-root" class="intranet">';

$rootFront = $CFG_GLPI['root_doc'].'/plugins/intranet/front';

/* =========================
   Notícias (Top 3 — sem filtro por datas)
   ========================= */
$sqlNews = "
  SELECT n.id, n.title, n.content, n.banner,
         n.date_publication, n.date_expiration,
         c.name AS cat_name
  FROM glpi_intranet_news n
  LEFT JOIN glpi_intranet_news_categories c ON c.id = n.category_id
  ORDER BY (n.date_publication IS NULL) ASC, n.date_publication DESC, n.id DESC
  LIMIT 3
";
$resNews = $DB->query($sqlNews);

/* =========================
   Aniversário (Fluxo do usuário + Lista próximos)
   ========================= */
// 1) Checa se o usuário logado tem birthday NULL
$uid = (int)Session::getLoginUserID();
$need_bday = false;
$user_name_display = $_SESSION['glpiname'] ?? ('Usuário #'.$uid);
if ($DB->fieldExists('glpi_users','birthday')) {
   $q = $DB->query("SELECT birthday FROM glpi_users WHERE id = {$uid} LIMIT 1");
   if ($q && $DB->numrows($q) === 1) {
      $row = $DB->fetchAssoc($q);
      $need_bday = empty($row['birthday']) || is_null($row['birthday']);
   }
}

// 2) Lista aniversariantes do mês atual e do próximo, com paginação
$bpage  = max(1, (int)($_GET['bpage'] ?? 1));
$limit  = 10;
$offset = ($bpage - 1) * $limit;

$sqlBirthWhere = "
  birthday IS NOT NULL
  AND (
       MONTH(birthday) = MONTH(CURDATE())
    OR MONTH(birthday) = MONTH(DATE_ADD(CURDATE(), INTERVAL 1 MONTH))
  )
";

// total
$resCount = $DB->query("SELECT COUNT(*) AS total FROM glpi_users WHERE {$sqlBirthWhere}");
$totalRows = ($resCount && $DB->numrows($resCount)) ? (int)$DB->result($resCount, 0, 'total') : 0;
$totalPages = max(1, (int)ceil($totalRows / $limit));

// lista
$resBirth = $DB->query("
  SELECT id, name, realname, firstname, birthday
  FROM glpi_users
  WHERE {$sqlBirthWhere}
  ORDER BY
    (MONTH(birthday) = MONTH(CURDATE())) DESC,
    MONTH(birthday) ASC,
    DAY(birthday) ASC,
    realname ASC
  LIMIT {$limit} OFFSET {$offset}
");

/* =========================
   Reservas (do mês)
   ========================= */
$resResv = $DB->query("
  SELECT r.id, ri.name, r.begin, r.end
  FROM glpi_reservations r
  JOIN glpi_reservationitems ri ON ri.id = r.reservationitems_id
  WHERE r.begin >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
  ORDER BY r.begin ASC
  LIMIT 50
");
$reservedDays = [];
if ($resResv) {
  while ($r = $DB->fetchAssoc($resResv)) {
    $reservedDays[(int)date('j', strtotime($r['begin']))] = true;
  }
  $resResv = $DB->query("
    SELECT r.id, ri.name, r.begin, r.end
    FROM glpi_reservations r
    JOIN glpi_reservationitems ri ON ri.id = r.reservationitems_id
    WHERE r.begin >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ORDER BY r.begin ASC
    LIMIT 50
  ");
}

/* =========================
   Clima (fallback com cidade da config)
   ========================= */
$weather = (class_exists('PluginIntranetDashboard') && !empty($conf['weather_api_key']))
  ? PluginIntranetDashboard::getWeather($conf) : null;
$tempConf  = isset($weather['main']['temp']) ? round($weather['main']['temp']) : null;
$cityConf  = trim((string)($conf['weather_city'] ?? ''));

// Usuário
$userName = $user_name_display;

// Calendário
$monthName    = strftime('%B %Y');
$firstDayTime = strtotime(date('Y-m-01'));
$firstWeekday = (int)date('w', $firstDayTime);
$daysInMonth  = (int)date('t', $firstDayTime);
?>
<style>
  /* News */
  .mini-news-list .card{display:flex;flex-direction:row;align-items:flex-start;gap:12px;flex-wrap:nowrap;padding:12px;border:1px solid #dfe7f6;border-radius:12px;background:#fff;margin-bottom:10px}
  .mini-news-list .thumb{width:78px;height:78px;border-radius:10px;overflow:hidden;flex:0 0 78px;background:#eef3ff;border:1px solid #e1e7f5;display:flex;align-items:center;justify-content:center;font-size:12px;color:#7a8aa0}
  .mini-news-list .thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .mini-news-list .meta{font-size:12px;color:#6b7a99;margin-bottom:3px}
  .mini-news-list .title{font-size:14px;line-height:1.25;margin:2px 0 6px;color:#0b2a5a;font-weight:700}
  .mini-news-list .excerpt{font-size:12px;color:#334;margin-bottom:8px}
  .mini-news-list .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .mini-news-list .btn-primary,.mini-news-list .btn-outline{font-size:12px}
  .mini-news-list .btn-primary{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9px;background:#0b2a5a;color:#fff;text-decoration:none}
  .mini-news-list .btn-outline{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9px;border:1px solid #0b2a5a;background:#fff;color:#0b2a5a;text-decoration:none}
  @media(max-width:560px){.mini-news-list .thumb{width:64px;height:64px;flex-basis:64px}}

  /* Clima */
  .weather-wrap{display:flex;align-items:center;gap:10px}
  .weather-icon{font-size:28px;line-height:1}
  .weather-info{display:flex;flex-direction:column}
  .weather-city{font-size:12px;color:#6b7a99;margin-bottom:2px;white-space:nowrap}
  .weather-temp{font-size:28px;font-weight:700;color:#0b2a5a;line-height:1}
  .weather-greet{font-size:12px;color:#677;white-space:nowrap;margin-top:6px}

  /* Aniversários */
  .birthday-form{display:flex;gap:8px;align-items:center;margin:8px 0}
  .birthday-form input[type="date"]{padding:6px 8px;border:1px solid #cfd8ea;border-radius:8px}
  .birthday-form button{padding:6px 10px;border-radius:8px;background:#0b2a5a;color:#fff;border:0;cursor:pointer}
  .birthday-list{list-style:none;padding:0;margin:0}
  .birthday-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #eef2fb}
  .birthday-item:last-child{border-bottom:0}
  .birthday-item .ava{width:36px;height:36px;border-radius:50%;overflow:hidden;background:#eef3ff;border:1px solid #e1e7f5;flex:0 0 36px}
  .birthday-item .ava img{width:100%;height:100%;object-fit:cover;display:block}
  .birthday-item .lines{display:flex;flex-direction:column;line-height:1.2}
  .birthday-item .lines .l1{font-weight:600;color:#0b2a5a}
  .birthday-item .lines .l2{font-size:12px;color:#6b7a99}
  .birthday-item .date{margin-left:auto;font-size:12px;color:#334}
  .pager{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}
  .pager a,.pager span{font-size:12px;padding:4px 8px;border:1px solid #cfd8ea;border-radius:8px;text-decoration:none;color:#0b2a5a;background:#fff}
  .pager .current{background:#0b2a5a;color:#fff;border-color:#0b2a5a}
</style>

<div class="intranet-grid">
  <div class="intra-left">
    <div class="box">
      <?php if (!empty($conf['banner'])): ?>
        <img class="banner" src="<?=$CFG_GLPI['root_doc']?>/plugins/intranet/banner/banner.jpg?f=<?=urlencode($conf['banner'])?>" alt="Banner">
      <?php endif; ?>
      <div class="welcome" style="margin-top:10px;">
        <h2>Bem-vindo à Intranet!</h2>
        <p>Olá, <?=Html::entities_deep($userName)?> 👋</p>
        <p>Fique por dentro das últimas novidades e informações da empresa.</p>
        <div class="btns">
          <?php for ($i=1;$i<=3;$i++):
            $lbl = $conf["btn{$i}_label"] ?? "Botão {$i}";
            $lnk = $conf["btn{$i}_link"]  ?? "";
            if (!$lnk) continue; ?>
            <a class="btn" target="_blank" href="<?=Html::entities_deep($lnk)?>"><?=Html::entities_deep($lbl)?></a>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="box">
      <h3>Notícias Internas</h3>
      <div class="mini-news-list">
        <?php if ($resNews && $DB->numrows($resNews)>0): ?>
          <?php while ($n = $DB->fetchAssoc($resNews)):
            $banner = trim($n['banner'] ?? '');
            $cat    = trim($n['cat_name'] ?? '');
            $when   = !empty($n['date_publication']) ? Html::entities_deep(date('d/m/Y H:i', strtotime($n['date_publication']))) : '—';
            $view   = $rootFront.'/news.view.php?id='.(int)$n['id'];
            $share  = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$rootFront.'/news.view.php?id='.(int)$n['id'];
          ?>
            <div class="card">
              <div class="thumb">
                <?php if ($banner !== ''): ?>
                  <img src="<?=Html::cleanInputText($banner)?>" alt="thumb">
                <?php else: ?>sem banner<?php endif; ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div class="meta">
                  <b>Publicada:</b> <?=$when?>
                  <?php if ($cat !== ''): ?> &nbsp; <b>Categoria:</b> <?=Html::entities_deep($cat)?><?php endif; ?>
                </div>
                <div class="title"><?=Html::entities_deep($n['title'])?></div>
                <div class="excerpt">
                  <?=Html::entities_deep(mb_strimwidth(strip_tags((string)$n['content']), 0, 170, '…', 'UTF-8'))?>
                </div>
                <div class="actions">
                  <a class="btn-primary" href="<?=$view?>">👁️ Ver</a>
                  <button class="btn-outline" type="button"
                          onclick="navigator.clipboard.writeText('<?=$share?>').then(()=>alert('Link copiado!')).catch(()=>alert('Não foi possível copiar.')); ">
                    🔗 Compartilhar
                  </button>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="news-empty">Sem notícias por enquanto.</div>
        <?php endif; ?>
      </div>
      <div class="news-actions" style="margin-top:10px;">
        <a class="btn secondary" href="<?=$rootFront?>/news.php">Ver todas</a>
      </div>
    </div>
  </div>

  <div class="intra-right" id="right-col">
    <?php if (isset($_GET['saved'])): ?>
      <div style="margin-bottom:8px;padding:8px 10px;border:1px solid #cfe8d1;background:#eefaf0;color:#276738;border-radius:8px;font-size:12px;">
        ✅ Data de nascimento salva com sucesso.
      </div>
    <?php endif; ?>

    <div class="box">
      <h4>Clima</h4>
      <div class="weather-wrap">
        <div class="weather-icon">☀</div>
        <div class="weather-info">
          <div class="weather-city" id="weather-city">
            <?= Html::entities_deep($cityConf ?: 'Cidade') ?>
          </div>
          <div class="weather-temp" id="weather-temp">
            <?= !is_null($tempConf) ? $tempConf.'°C' : '—' ?>
          </div>
        </div>
      </div>
      <div class="weather-greet" id="weather-greet">Bom dia, <?=Html::entities_deep($userName)?>!</div>

      <script>
        (function(){
          if (!navigator.geolocation) return;
          navigator.geolocation.getCurrentPosition(function(pos){
            var lat = pos.coords.latitude, lon = pos.coords.longitude;
            fetch('<?= $CFG_GLPI['root_doc'] ?>/plugins/intranet/front/dashboard.php?ajax=weather&lat='+encodeURIComponent(lat)+'&lon='+encodeURIComponent(lon), {
              credentials: 'same-origin'
            })
            .then(r=>r.json())
            .then(function(d){
              if (!d || !d.ok) return;
              var cityEl = document.getElementById('weather-city');
              var tempEl = document.getElementById('weather-temp');
              if (cityEl && d.city) cityEl.textContent = d.city;
              if (tempEl && (typeof d.temp !== 'undefined' && d.temp !== null)) tempEl.textContent = d.temp + '°C';
            })
            .catch(function(){});
          }, function(){}, {timeout:8000});
        })();
      </script>
    </div>

    <div class="box" id="box-birthday">
      <h4>Aniversariantes</h4>

      <?php if ($need_bday): ?>
        <div id="bday-form-wrap">
          <div style="font-size:12px;color:#6b7a99;margin-bottom:6px">Qual a sua data de Nascimento?</div>
          <div class="birthday-form">
            <input type="date" id="bday-input" max="<?=Html::entities_deep(date('Y-m-d'))?>" required>
            <button type="button" id="bday-save">Salvar</button>
          </div>
          <div id="bday-msg" style="font-size:12px;color:#6b7a99;display:none"></div>
        </div>
        <script>
          (function(){
            var btn = document.getElementById('bday-save');
            var inp = document.getElementById('bday-input');
            var msg = document.getElementById('bday-msg');
            var selfUrl = '<?= Html::entities_deep($_SERVER['PHP_SELF']) ?>';

            if (!btn || !inp) return;
            btn.addEventListener('click', function(){
              var v = (inp.value||'').trim();
              if (!v) { msg.style.display='block'; msg.textContent='Selecione uma data.'; msg.style.color='#c00'; return; }
              var url = selfUrl + '?ajax=birthday_save&salvar=1&date=' + encodeURIComponent(v) + '&_ts=' + Date.now();
              fetch(url, { credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                  if (d && d.ok) {
                    var wrap = document.getElementById('bday-form-wrap');
                    if (wrap) wrap.style.display = 'none';
                    msg.style.display='block'; msg.style.color='#0a8'; msg.textContent='Data salva!';
                    // redirect para refletir lista e manter âncora
                    setTimeout(function(){
                      window.location.replace(selfUrl + '?saved=1&_ts=' + Date.now() + '#box-birthday');
                    }, 350);
                  } else {
                    msg.style.display='block'; msg.style.color='#c00';
                    msg.textContent = (d && d.msg) ? d.msg : 'Falha ao salvar.';
                  }
                })
                .catch(function(){
                  msg.style.display='block'; msg.style.color='#c00'; msg.textContent='Erro de comunicação.';
                });
            });
          })();
        </script>
      <?php endif; ?>

      <ul class="birthday-list" id="birthday-list">
        <?php if ($resBirth && $DB->numrows($resBirth) > 0): ?>
          <?php while ($u = $DB->fetchAssoc($resBirth)):
             $uidx   = (int)$u['id'];
             $name   = intranet_get_user_display_name($u, $uidx);
             $dateDM = !empty($u['birthday']) ? date('d/m', strtotime($u['birthday'])) : '--/--';
             $ava    = intranet_get_user_avatar_url($uidx);
          ?>
            <li class="birthday-item">
                <div class="ava">
                  <img
                    src="<?= Html::entities_deep($ava) ?>"
                    alt="avatar"
                    onerror="(function(img){
                      // fallback do *_min.png -> original sem _min
                      var u = img.getAttribute('src')||'';
                      if (u.indexOf('_min.') !== -1) {
                        img.onerror = null; // evita loop
                        img.src = u.replace('_min.', '.');
                        return;
                      }
                      // último fallback: ícone padrão do GLPI
                      img.onerror = null;
                      img.src = '<?= Html::entities_deep(rtrim($CFG_GLPI['root_doc'], '/').'/pics/user.png') ?>';
                    })(this);"
                  >
</div>

              
              
              
              <div class="lines">
                <div class="l1"><?=Html::entities_deep($name)?></div>
                <div class="l2">Aniversário: <?=$dateDM?></div>
              </div>
              <div class="date"><?=$dateDM?></div>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="small" style="color:#6b7a99;">Nenhum aniversariante no período.</li>
        <?php endif; ?>
      </ul>

      <?php if ($totalPages > 1): ?>
        <div class="pager">
          <?php
            $base = Html::entities_deep($_SERVER['PHP_SELF']);
            $prev = max(1, $bpage-1);
            $next = min($totalPages, $bpage+1);
          ?>
          <?php if ($bpage > 1): ?>
            <a href="<?=$base?>?bpage=<?=$prev?>#box-birthday">&laquo; Anterior</a>
          <?php endif; ?>
          <span class="current"><?=$bpage?> / <?=$totalPages?></span>
          <?php if ($bpage < $totalPages): ?>
            <a href="<?=$base?>?bpage=<?=$next?>#box-birthday">Próxima &raquo;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="box">
      <h4>Calendário / Reservas</h4>
      <table class="tab_cadre" style="width:100%; text-align:center;">
        <tr><th colspan="7" style="text-transform:capitalize;"><?=Html::entities_deep($monthName)?></th></tr>
        <tr><th>Dom</th><th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sáb</th></tr>
        <?php
          $day=1; $dow=$firstWeekday; echo '<tr>';
          for ($i=0; $i<$dow; $i++) echo '<td></td>';
          while ($day <= $daysInMonth) {
            $cls = isset($reservedDays[$day]) ? 'style="font-weight:bold;border:1px solid #0b2a5a;border-radius:6px;"' : '';
            echo "<td $cls>$day</td>";
            $dow++;
            if ($dow > 6) { echo '</tr><tr>'; $dow=0; }
            $day++;
          }
          if ($dow > 0) { for ($i=$dow; $i<=6; $i++) echo '<td></td>'; echo '</tr>'; } else { echo '</tr>'; }
        ?>
      </table>
      <div style="margin-top:10px;">
        <ul class="list">
          <?php if ($resResv && $DB->numrows($resResv)>0): ?>
            <?php while ($r = $DB->fetchAssoc($resResv)): ?>
              <li><span class="date"><?=Html::convDateTime($r['begin'])?></span>
                  <span><?=Html::entities_deep($r['name'])?></span></li>
            <?php endwhile; ?>
          <?php else: ?>
            <li class="small">Sem reservas futuras.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
echo '</div>'; // fecha #intranet-root
Html::footer();
