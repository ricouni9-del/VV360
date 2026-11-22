// Registra de onde veio o visitante (Google Ads, Instagram, busca...) para o CRM
try{if(!sessionStorage.getItem('vv_canal')){var q=new URLSearchParams(location.search),s=(q.get('utm_source')||'').toLowerCase(),m=(q.get('utm_medium')||'').toLowerCase(),r=document.referrer||'',c='Site';
if(q.get('gclid')||(s==='google'&&/cpc|ads|paid/.test(m)))c='Google Ads';else if(/instagram/.test(s)||/instagram\.com/.test(r))c='Instagram';
else if(/facebook|fb/.test(s)||/facebook\.com/.test(r))c='Facebook';else if(/google\./.test(r))c='Busca Google';sessionStorage.setItem('vv_canal',c);}}catch(e){}
