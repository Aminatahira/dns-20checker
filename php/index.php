<?php
// DNS Suite – Bootstrap PHP (cPanel-ready)
// Full functionality: single/bulk lookup, record select (All), resolver (System/Cloudflare/Google), WHOIS

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function is_ip($s){return filter_var($s,FILTER_VALIDATE_IP)!==false;}
function asset_url($path){
  $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
  return ($base?:'').'/'.ltrim($path,'/');
}

function doh_query($name,$type,$provider='cloudflare'){
  $type=strtoupper($type);$provider=strtolower($provider);
  $endpoint=$provider==='google'
    ? 'https://dns.google/resolve?name='.rawurlencode($name).'&type='.rawurlencode($type)
    : 'https://cloudflare-dns.com/dns-query?name='.rawurlencode($name).'&type='.rawurlencode($type);
  $ch=curl_init();
  curl_setopt_array($ch,[CURLOPT_URL=>$endpoint,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Accept: application/dns-json'],CURLOPT_TIMEOUT=>12]);
  $resp=curl_exec($ch);if($resp===false){$err=curl_error($ch);curl_close($ch);return ['error'=>'DoH failed: '.$err];}
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($code<200||$code>=300)return ['error'=>'DoH HTTP '.$code];
  $json=json_decode($resp,true);if(!is_array($json))return ['error'=>'Invalid DoH JSON'];return $json;
}
function whois_tcp($server,$query){$fp=@fsockopen($server,43,$errno,$errstr,10);if(!$fp)return "WHOIS connection error: $errstr ($errno)";fwrite($fp,$query."\r\n");$out='';while(!feof($fp)){$out.=fgets($fp,1024);}fclose($fp);return $out;}
function whois_lookup($query){$tld=$query;if(strpos($query,'.')!==false){$parts=explode('.',$query);$tld=end($parts);} $iana=whois_tcp('whois.iana.org',$tld);if(preg_match('/whois:\\s*([^\\s]+)/i',$iana,$m)){$server=trim($m[1]);$raw=whois_tcp($server,$query);return ['server'=>$server,'raw'=>$raw];}return ['server'=>'whois.iana.org','raw'=>whois_tcp('whois.iana.org',$query)];}
function php_dns_record($domain,$type){$map=['A'=>DNS_A,'AAAA'=>DNS_AAAA,'CNAME'=>DNS_CNAME,'MX'=>DNS_MX,'NS'=>DNS_NS,'TXT'=>DNS_TXT,'SRV'=>DNS_SRV,'SOA'=>DNS_SOA,'CAA'=>defined('DNS_CAA')?DNS_CAA:null,'DS'=>defined('DNS_DS')?DNS_DS:null,'DNSKEY'=>defined('DNS_DNSKEY')?DNS_DNSKEY:null,];$flag=$map[$type]??null;if($type==='PTR'){if(is_ip($domain)){$host=gethostbyaddr($domain);return $host?[['ptr'=>$host]]:[];}return [];} if($flag===null)return false;$out=@dns_get_record($domain,$flag);if($out===false)return false;return $out;}
function resolve_by_type($domain,$type,$provider){$type=strtoupper($type);if($provider!=='system'||in_array($type,['DNSKEY','DS'],true)){return doh_query($domain,$type,$provider==='google'?'google':'cloudflare');}$sys=php_dns_record($domain,$type);if($sys===false){return doh_query($domain,$type,'cloudflare');}return $sys;}

$ALL_TYPES=['A','AAAA','CNAME','MX','NS','TXT','SRV','SOA','CAA','PTR','DNSKEY','DS'];
$domain=isset($_POST['domain'])?trim($_POST['domain']):'';
$record=isset($_POST['record'])?strtoupper(trim($_POST['record'])):'A';
$provider=isset($_POST['provider'])?strtolower(trim($_POST['provider'])):'system';
$bulk=isset($_POST['bulk'])?trim($_POST['bulk']):'';
$action=isset($_POST['action'])?$_POST['action']:'';
$results=null;$bulkResults=null;$whois=null; $active_tab='single';
if($action==='lookup'&&$domain!==''){ $types=$record==='ALL'?$ALL_TYPES:[$record];$map=[];foreach($types as $t){$map[$t]=resolve_by_type($domain,$t,$provider);} $results=['domain'=>$domain,'provider'=>$provider,'results'=>$map]; $active_tab='single'; }
if($action==='whois'&&$domain!==''){ $whois=whois_lookup($domain); $active_tab='single'; }
if($action==='bulk'&&$bulk!==''){ $domains=preg_split('/\s+/', $bulk,-1,PREG_SPLIT_NO_EMPTY);$types=$record==='ALL'?$ALL_TYPES:[$record];$bulkMap=[];foreach($domains as $d){$d=trim($d);if($d==='')continue;$item=[];foreach($types as $t){$item[$t]=resolve_by_type($d,$t,$provider);} $bulkMap[$d]=$item;} $bulkResults=['provider'=>$provider,'results'=>$bulkMap]; $active_tab='bulk'; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DNS Suite – Advanced DNS Records Checker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= h(asset_url('assets/styles.css')) ?>" />
  <style>
    :root{--brand-700:258 80% 48%;--brand-600:258 85% 54%}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
    .brand-logo{width:32px;height:32px;background:linear-gradient(135deg,hsl(var(--brand-600)),hsl(var(--brand-700)));color:#fff;font-weight:800}
    .font-mono,.font-monospace{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  </style>
  <script>
    (function(){
      function injectLocal(){
        var l=document.createElement('link');l.rel='stylesheet';l.href='<?= h(asset_url('assets/bootstrap.min.css')) ?>';document.head.appendChild(l);
      }
      function check(){
        var v=getComputedStyle(document.documentElement).getPropertyValue('--bs-body-font-family');
        if(!v||!v.trim()){injectLocal();}
      }
      if(document.readyState!=='loading'){check();} else {document.addEventListener('DOMContentLoaded',check);}
    })();
  </script>
</head>
<body>
  <nav class="navbar navbar-expand bg-body bg-opacity-75 border-bottom sticky-top">
    <div class="container py-2">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <span class="d-inline-flex align-items-center justify-content-center rounded brand-logo">D</span>
        <span class="fw-semibold">DNS Suite</span>
      </a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <a class="btn btn-secondary" href="#">Checker</a>
        <a class="link-secondary text-decoration-none" target="_blank" rel="noreferrer" href="https://builder.io/c/docs/projects">Docs</a>
      </div>
    </div>
  </nav>

  <section class="py-5 text-white position-relative overflow-hidden" style="background:linear-gradient(135deg,hsl(258 80% 48%),hsl(258 85% 54%));">
    <div class="position-absolute top-50 start-50 translate-middle opacity-25" style="width:560px;height:560px;border-radius:50%;background:#fff;filter:blur(70px);"></div>
    <div class="container position-relative">
      <h1 class="display-5 fw-bold">Advanced DNS Records Checker</h1>
      <p class="lead opacity-75">Check A, AAAA, MX, TXT, NS, CNAME, PTR, SRV, SOA, CAA, DNSKEY, DS. Choose resolver, single or bulk, with WHOIS.</p>

      <ul class="nav nav-pills mt-4" id="dnsTabs">
        <li class="nav-item"><button class="nav-link <?= $active_tab==='single'?'active':'' ?>" data-tab="single" type="button">Single Lookup</button></li>
        <li class="nav-item"><button class="nav-link <?= $active_tab==='bulk'?'active':'' ?>" data-tab="bulk" type="button">Bulk Lookup</button></li>
      </ul>

      <div class="tab-content pt-3">
        <div id="tab-single" class="tab-pane fade <?= $active_tab==='single'?'show active':'' ?>">
          <form method="post" class="bg-white bg-opacity-10 border border-white border-opacity-25 rounded-3 p-3">
            <input type="hidden" name="action" value="lookup" />
            <div class="row g-2 align-items-center">
              <div class="col-md-7">
                <input class="form-control text-white bg-white bg-opacity-10 border-white border-opacity-25" name="domain" placeholder="Enter domain (or IP for PTR)" value="<?= h($domain?:'example.com') ?>" />
              </div>
              <div class="col-md-3">
                <select class="form-select text-white bg-white bg-opacity-10 border-white border-opacity-25" name="record">
                  <option value="ALL" <?= $record==='ALL'?'selected':'' ?>>All types</option>
                  <?php foreach($ALL_TYPES as $t): ?>
                    <option value="<?= h($t) ?>" <?= $record===$t?'selected':'' ?>><?= h($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 text-end">
                <button class="btn btn-light text-primary fw-semibold shadow-sm w-100" type="submit">Check DNS</button>
              </div>
            </div>
            <div class="row g-2 align-items-center mt-2">
              <div class="col-md-8">
                <div class="btn-group" role="group" aria-label="Resolver">
                  <input type="radio" class="btn-check" name="provider" id="prov-system" value="system" <?= $provider==='system'?'checked':'' ?> />
                  <label class="btn btn-outline-light" for="prov-system">System</label>
                  <input type="radio" class="btn-check" name="provider" id="prov-cloudflare" value="cloudflare" <?= $provider==='cloudflare'?'checked':'' ?> />
                  <label class="btn btn-outline-light" for="prov-cloudflare">Cloudflare</label>
                  <input type="radio" class="btn-check" name="provider" id="prov-google" value="google" <?= $provider==='google'?'checked':'' ?> />
                  <label class="btn btn-outline-light" for="prov-google">Google</label>
                </div>
              </div>
              <div class="col-md-4 text-end">
                <button class="btn btn-light text-dark" type="submit" onclick="this.form.action.value='whois'">WHOIS</button>
              </div>
            </div>
          </form>
        </div>

        <div id="tab-bulk" class="tab-pane fade <?= $active_tab==='bulk'?'show active':'' ?>">
          <form method="post" class="bg-white bg-opacity-10 border border-white border-opacity-25 rounded-3 p-3">
            <input type="hidden" name="action" value="bulk" />
            <div class="row g-2">
              <div class="col-md-8">
                <textarea class="form-control text-white bg-white bg-opacity-10 border-white border-opacity-25" name="bulk" rows="4" placeholder="domain.com&#10;example.org"><?= h($bulk) ?></textarea>
              </div>
              <div class="col-md-4">
                <div class="row g-2">
                  <div class="col-12">
                    <select class="form-select text-white bg-white bg-opacity-10 border-white border-opacity-25" name="record">
                      <option value="ALL" <?= $record==='ALL'?'selected':'' ?>>All types</option>
                      <?php foreach($ALL_TYPES as $t): ?>
                        <option value="<?= h($t) ?>" <?= $record===$t?'selected':'' ?>><?= h($t) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <div class="btn-group w-100" role="group">
                      <input type="radio" class="btn-check" name="provider" id="bprov-system" value="system" <?= $provider==='system'?'checked':'' ?> />
                      <label class="btn btn-outline-light" for="bprov-system">System</label>
                      <input type="radio" class="btn-check" name="provider" id="bprov-cloudflare" value="cloudflare" <?= $provider==='cloudflare'?'checked':'' ?> />
                      <label class="btn btn-outline-light" for="bprov-cloudflare">Cloudflare</label>
                      <input type="radio" class="btn-check" name="provider" id="bprov-google" value="google" <?= $provider==='google'?'checked':'' ?> />
                      <label class="btn btn-outline-light" for="bprov-google">Google</label>
                    </div>
                  </div>
                  <div class="col-12 text-end">
                    <button class="btn btn-light text-primary fw-semibold shadow-sm" type="submit">Run Bulk</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <?php if($whois): ?>
        <div class="card shadow mt-4">
          <div class="card-body">
            <h5 class="card-title mb-1">WHOIS</h5>
            <div class="text-muted mb-2">Server: <span class="font-monospace small"><?= h($whois['server']??'') ?></span></div>
            <pre class="bg-light p-3 rounded border small" style="white-space:pre-wrap;max-height:480px;overflow:auto;"><?= h($whois['raw']??'') ?></pre>
          </div>
        </div>
      <?php endif; ?>

      <?php if($results): ?>
        <div class="card shadow mt-4">
          <div class="card-body">
            <h5 class="card-title mb-1">Results</h5>
            <div class="text-muted mb-3">Provider: <span class="font-monospace small"><?= h($results['provider']) ?></span> · Domain: <span class="font-monospace small"><?= h($results['domain']) ?></span></div>
            <?php foreach($results['results'] as $type=>$value): ?>
              <h6 class="fw-semibold mt-3 mb-2"><?= h($type) ?></h6>
              <?= render_value($type,$value) ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if($bulkResults): ?>
        <div class="card shadow mt-4">
          <div class="card-body">
            <h5 class="card-title mb-1">Bulk Results</h5>
            <div class="text-muted mb-3">Provider: <span class="font-monospace small"><?= h($bulkResults['provider']) ?></span></div>
            <?php foreach($bulkResults['results'] as $d=>$recordMap): ?>
              <h6 class="fw-semibold mt-3 mb-2"><?= h($d) ?></h6>
              <?php foreach($recordMap as $t=>$v): ?>
                <div class="mb-2">
                  <div class="small text-muted mb-1"><?= h($t) ?></div>
                  <?= render_value($t,$v) ?>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <footer class="border-top bg-body">
    <div class="container py-4 text-secondary d-flex justify-content-between">
      <div>© <?= date('Y') ?> DNS Suite</div>
      <div class="small">Advanced DNS tools with an elegant UI.</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>(function(){if(!window.bootstrap){var s=document.createElement('script');s.src='<?= h(asset_url('assets/bootstrap.bundle.min.js')) ?>';document.body.appendChild(s);}})();</script>
  <script src="<?= h(asset_url('assets/app.js')) ?>"></script>
  <script>
    (function(){
      const tabs=document.getElementById('dnsTabs');if(!tabs)return;const single=document.getElementById('tab-single');const bulk=document.getElementById('tab-bulk');
      tabs.addEventListener('click',function(e){var btn=e.target.closest('[data-tab]');if(!btn)return;var tab=btn.getAttribute('data-tab');tabs.querySelectorAll('.nav-link').forEach(function(el){el.classList.remove('active');});btn.classList.add('active');if(tab==='single'){single.classList.add('show','active');bulk.classList.remove('show','active');}else{bulk.classList.add('show','active');single.classList.remove('show','active');}});
    })();
  </script>
</body>
</html>
<?php
function render_value($type,$value){
  if(is_string($value)||is_numeric($value))return '<div class="font-monospace">'.h($value).'</div>';
  if(is_array($value)){
    if(isset($value['Answer'])&&is_array($value['Answer'])){
      $rows=$value['Answer'];$out='<div class="table-responsive"><table class="table table-sm table-striped align-middle"><thead class="table-light"><tr><th>name</th><th>type</th><th>TTL</th><th>data</th></tr></thead><tbody>';
      foreach($rows as $a){$out.='<tr><td class="font-monospace small">'.h($a['name']??'').'</td><td class="font-monospace small">'.h($a['type']??'').'</td><td class="font-monospace small">'.h($a['TTL']??'').'</td><td class="font-monospace small">'.h($a['data']??'').'</td></tr>';}
      $out.='</tbody></table></div>';return $out;}
    if(!empty($value)&&array_keys($value)===range(0,count($value)-1)){
      $out='<div class="table-responsive"><table class="table table-sm align-middle"><thead class="table-light"><tr><th style="width:56px">#</th><th>Value</th></tr></thead><tbody>';$i=1;foreach($value as $row){$out.='<tr><td class="text-secondary">'.$i++.'</td><td>';if(is_array($row)){$out.=kv($row);}else{$out.='<span class="font-monospace small">'.h($row).'</span>';}$out.='</td></tr>';}$out.='</tbody></table></div>';return $out;}
    return kv($value);
  }
  return '<div class="font-monospace">'.h((string)$value).'</div>';
}
function kv($obj){$out='<div class="row g-1 small">';foreach($obj as $k=>$v){$out.='<div class="col-3 text-secondary">'.h($k).'</div><div class="col-9"><span class="font-monospace">';$out.=is_array($v)?h(json_encode($v)):h((string)$v);$out.='</span></div>';}$out.='</div>';return $out;}
?>
