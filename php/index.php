<?php
// DNS Suite - PHP version for cPanel (HTML/CSS/PHP)
// Single & bulk lookup, record type (or All), resolver (System/Cloudflare/Google), WHOIS

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function is_ip($s){return filter_var($s,FILTER_VALIDATE_IP)!==false;}

function doh_query($name,$type,$provider='cloudflare'){
  $type=strtoupper($type);$provider=strtolower($provider);
  $endpoint=$provider==='google'
    ? 'https://dns.google/resolve?name='.rawurlencode($name).'&type='.rawurlencode($type)
    : 'https://cloudflare-dns.com/dns-query?name='.rawurlencode($name).'&type='.rawurlencode($type);
  $ch=curl_init();
  curl_setopt_array($ch,[CURLOPT_URL=>$endpoint,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Accept: application/dns-json'],CURLOPT_TIMEOUT=>12]);
  $resp=curl_exec($ch);if($resp===false){$err=curl_error($ch);curl_close($ch);return ['error'=>'DoH failed: '.$err];}
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($code<200||$code>=300)return ['error'=>'DoH HTTP '.$code];
  $json=json_decode($resp,true);if(!is_array($json))return ['error'=>'Invalid DoH JSON'];return $json;}

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
$results=null;$bulkResults=null;$whois=null;
if($action==='lookup'&&$domain!==''){ $types=$record==='ALL'?$ALL_TYPES:[$record];$map=[];foreach($types as $t){$map[$t]=resolve_by_type($domain,$t,$provider);} $results=['domain'=>$domain,'provider'=>$provider,'results'=>$map]; }
if($action==='whois'&&$domain!==''){ $whois=whois_lookup($domain); }
if($action==='bulk'&&$bulk!==''){ $domains=preg_split('/\s+/', $bulk,-1,PREG_SPLIT_NO_EMPTY);$types=$record==='ALL'?$ALL_TYPES:[$record];$bulkMap=[];foreach($domains as $d){$d=trim($d);if($d==='')continue;$item=[];foreach($types as $t){$item[$t]=resolve_by_type($d,$t,$provider);} $bulkMap[$d]=$item;} $bulkResults=['provider'=>$provider,'results'=>$bulkMap]; }
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>DNS Suite – Advanced DNS Records Checker</title>
<link rel="preconnect" href="https://fonts.googleapis.com" /><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/styles.css" />
</head><body>
<header class="site-header"><div class="container header-inner"><div class="brand"><div class="logo">D</div><div class="title">DNS Suite</div></div><nav class="nav"><a class="btn btn-secondary" href="#">Checker</a><a class="link" target="_blank" rel="noreferrer" href="https://builder.io/c/docs/projects">Docs</a></nav></div></header>
<main>
<section class="hero"><div class="container">
<h1>Advanced DNS Records Checker</h1>
<p class="lead">Check A, AAAA, MX, TXT, NS, CNAME, PTR, SRV, SOA, CAA, DNSKEY, DS records. Choose resolver, single or bulk, with WHOIS.</p>
<form method="post" class="form"><input type="hidden" name="action" value="lookup" />
  <div class="row">
    <div class="col col-grow">
      <input class="input" name="domain" placeholder="Enter domain (or IP for PTR)" value="<?= h($domain?:'example.com') ?>" />
    </div>
    <div class="col">
      <select class="select" name="record">
        <option value="ALL" <?= $record==='ALL'?'selected':'' ?>>All types</option>
        <?php foreach($ALL_TYPES as $t): ?>
          <option value="<?= h($t) ?>" <?= $record===$t?'selected':'' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="row">
    <div class="provider">
      <button type="submit" name="provider" value="system" class="chip <?= $provider==='system'?'active':'' ?>">System</button>
      <button type="submit" name="provider" value="cloudflare" class="chip <?= $provider==='cloudflare'?'active':'' ?>">Cloudflare</button>
      <button type="submit" name="provider" value="google" class="chip <?= $provider==='google'?'active':'' ?>">Google</button>
    </div>
    <div class="actions">
      <button class="btn btn-primary" type="submit" onclick="this.form.action.value='lookup'">Check DNS</button>
      <button class="btn btn-outline" type="submit" onclick="this.form.action.value='whois'">WHOIS</button>
    </div>
  </div>
</form>
<form method="post" class="form form-bulk"><input type="hidden" name="action" value="bulk" /><input type="hidden" name="provider" value="<?= h($provider) ?>" />
  <div class="row">
    <div class="col col-grow"><textarea class="textarea" name="bulk" rows="4" placeholder="domain.com&#10;example.org"><?= h($bulk) ?></textarea></div>
    <div class="col">
      <select class="select" name="record">
        <option value="ALL" <?= $record==='ALL'?'selected':'' ?>>All types</option>
        <?php foreach($ALL_TYPES as $t): ?>
          <option value="<?= h($t) ?>" <?= $record===$t?'selected':'' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="mt-2"><button class="btn" type="submit">Run Bulk</button></div>
    </div>
  </div>
</form>
<?php if($whois): ?>
  <div class="card"><div class="card-header"><div class="card-title">WHOIS</div><div class="card-sub">Server: <span class="mono"><?= h($whois['server']??'') ?></span></div></div><div class="card-body"><pre class="pre"><?= h($whois['raw']??'') ?></pre></div></div>
<?php endif; ?>
<?php if($results): ?>
  <div class="card"><div class="card-header"><div class="card-title">Results</div><div class="card-sub">Provider: <span class="mono"><?= h($results['provider']) ?></span> · Domain: <span class="mono"><?= h($results['domain']) ?></span></div></div><div class="card-body">
  <?php foreach($results['results'] as $type=>$value): ?>
    <div class="section"><div class="section-title"><?= h($type) ?></div><?= render_value($type,$value) ?></div>
  <?php endforeach; ?>
  </div></div>
<?php endif; ?>
<?php if($bulkResults): ?>
  <div class="card"><div class="card-header"><div class="card-title">Bulk Results</div><div class="card-sub">Provider: <span class="mono"><?= h($bulkResults['provider']) ?></span></div></div><div class="card-body">
  <?php foreach($bulkResults['results'] as $d=>$recordMap): ?>
    <div class="section"><div class="section-title"><?= h($d) ?></div>
      <?php foreach($recordMap as $t=>$v): ?>
        <div class="subsection"><div class="subsection-title"><?= h($t) ?></div><?= render_value($t,$v) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </div></div>
<?php endif; ?>
</div></section>
</main>
<footer class="site-footer"><div class="container footer-inner"><div>© <?= date('Y') ?> DNS Suite</div><div class="muted">Advanced DNS tools with an elegant UI.</div></div></footer>
</body></html>
<?php
function render_value($type,$value){
  if(is_string($value)||is_numeric($value))return '<div class="mono">'.h($value).'</div>';
  if(is_array($value)){
    if(isset($value['Answer'])&&is_array($value['Answer'])){
      $rows=$value['Answer'];$out='<table class="table"><thead><tr><th>name</th><th>type</th><th>TTL</th><th>data</th></tr></thead><tbody>';
      foreach($rows as $a){$out.='<tr><td class="mono s">'.h($a['name']??'').'</td><td class="mono s">'.h($a['type']??'').'</td><td class="mono s">'.h($a['TTL']??'').'</td><td class="mono s">'.h($a['data']??'').'</td></tr>';}
      $out.='</tbody></table>';return $out;}
    if(!empty($value)&&array_keys($value)===range(0,count($value)-1)){
      $out='<table class="table"><thead><tr><th>#</th><th>Value</th></tr></thead><tbody>';$i=1;foreach($value as $row){$out.='<tr><td class="muted">'.$i++.'</td><td>';if(is_array($row)){$out.=kv($row);}else{$out.='<span class="mono">'.h($row).'</span>';}$out.='</td></tr>';}$out.='</tbody></table>';return $out;}
    return kv($value);
  }
  return '<div class="mono">'.h((string)$value).'</div>';
}
function kv($obj){$out='<div class="kv">';foreach($obj as $k=>$v){$out.='<div class="kv-row"><div class="kv-k">'.h($k).'</div><div class="kv-v mono">';$out.=is_array($v)?h(json_encode($v)):h((string)$v);$out.='</div></div>';} $out.='</div>';return $out;}
?>
