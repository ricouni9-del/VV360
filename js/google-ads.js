/* ============================================================
   Google Ads · conversões da Vanessa Viagens e Turismo
   PREENCHA com os dados da sua ação de conversão no Google Ads
   (Metas > Conversões > ação "Pedido de proposta" > Configurar tag).
   Enquanto "id" estiver vazio, nada é carregado e nenhum aviso aparece.
   ============================================================ */
var VV_ADS = {
  id: '',              // ID da conversão, ex.: 'AW-123456789'
  rotuloProposta: '',  // rótulo da conversão "Pedido de proposta", ex.: 'AbCdEfGhIjKlMn'
  rotuloContato: ''    // opcional: rótulo de uma conversão secundária "Fale com a gente"
};

(function () {
  // Guarda o identificador do clique no anúncio (gclid) para o CRM, mesmo sem cookies
  try {
    var q = new URLSearchParams(location.search);
    if (q.get('gclid')) sessionStorage.setItem('vv_gclid', q.get('gclid'));
  } catch (e) {}

  window.vvConversao = function () {};
  window.vvPreferenciasCookies = function () {};
  if (!VV_ADS.id) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { dataLayer.push(arguments); };
  var escolha = null; try { escolha = localStorage.getItem('vv_consent'); } catch (e) {}
  var neg = { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' };
  var ok = { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'denied', analytics_storage: 'denied' };
  gtag('consent', 'default', escolha === 'sim' ? ok : Object.assign({ wait_for_update: 500 }, neg));
  gtag('set', 'url_passthrough', true);
  gtag('js', new Date());
  gtag('config', VV_ADS.id);
  var s = document.createElement('script'); s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(VV_ADS.id);
  document.head.appendChild(s);

  window.vvConversao = function (tipo, transacao) {
    var rot = tipo === 'contato' ? VV_ADS.rotuloContato : VV_ADS.rotuloProposta;
    if (!rot) return;
    var dados = { send_to: VV_ADS.id + '/' + rot };
    if (transacao) dados.transaction_id = transacao;
    gtag('event', 'conversion', dados);
  };

  // Aviso de cookies (LGPD): os anúncios só usam cookies se o visitante aceitar
  function salvar(v) {
    try { localStorage.setItem('vv_consent', v); } catch (e) {}
    gtag('consent', 'update', v === 'sim' ? ok : neg);
    var b = document.getElementById('vv-cookies'); if (b) b.remove();
  }
  function mostrar() {
    if (document.getElementById('vv-cookies')) return;
    var b = document.createElement('div'); b.id = 'vv-cookies'; b.setAttribute('role', 'dialog'); b.setAttribute('aria-label', 'Preferências de cookies');
    b.style.cssText = 'position:fixed;left:16px;right:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:60;max-width:560px;margin:0 auto;background:#1B2A4A;color:#E4E8EF;border-radius:12px;padding:18px 20px;box-shadow:0 18px 40px rgba(0,0,0,.35);font:15px/1.5 "Segoe UI",system-ui,sans-serif';
    b.innerHTML = '<p style="margin:0 0 12px">Usamos cookies do Google apenas para saber quais anúncios trazem visitantes ao nosso site. Você escolhe. <a href="/privacidade.html" style="color:#DDE5F1">Saiba mais</a></p>' +
      '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap"><button type="button" data-v="nao" style="font:inherit;font-weight:600;padding:9px 16px;border-radius:6px;border:1.5px solid rgba(185,194,207,.6);background:none;color:#fff;cursor:pointer">Recusar</button>' +
      '<button type="button" data-v="sim" style="font:inherit;font-weight:600;padding:9px 16px;border-radius:6px;border:none;background:#fff;color:#1B2A4A;cursor:pointer">Aceitar</button></div>';
    b.addEventListener('click', function (e) { var v = e.target.getAttribute('data-v'); if (v) salvar(v); });
    document.body.appendChild(b);
  }
  window.vvPreferenciasCookies = mostrar;
  if (!escolha) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mostrar); else mostrar(); }
})();
