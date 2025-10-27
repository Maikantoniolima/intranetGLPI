// plugins/intranet/assets/js/pages/news-quill.init.js
(function () {
  'use strict';

  function isEl(el){ return el && el.nodeType===1 && typeof el.querySelector==='function'; }

  function ensurePair(ta){
    var cont = ta.parentElement && ta.parentElement.querySelector('.rte-quill');
    if (!isEl(cont)) {
      cont = document.createElement('div');
      cont.className = 'rte-quill';
      cont.style.height = '260px';
      ta.insertAdjacentElement('afterend', cont);
    }
    if (!cont.id) cont.id = 'rte_' + Math.random().toString(36).slice(2,10);
    return cont;
  }

  function createQuill(ta, cont){
    if (!window.Quill || !isEl(cont)) return false;
    try{
      var toolbar = [
        [{ font: [] }, { size: [] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ script: 'super' }, { script: 'sub' }],
        [{ header: [false,1,2,3,4,5,6] }, 'blockquote', 'code-block'],
        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
        ['direction', { align: [] }],
        ['link', 'image', 'video'],
        ['clean']
      ];

      var initial = ta.value || '';
      var q = new Quill('#'+cont.id, { theme:'snow', modules:{ toolbar: toolbar } });
      if (initial) q.clipboard.dangerouslyPasteHTML(initial);

      cont.dataset.qReady = '1';
      q.on('text-change', function(){ ta.value = q.root.innerHTML; });
      ta.value = q.root.innerHTML;
      return true;
    }catch(e){
      console.warn('[news-quill] falha init em #'+cont.id+':', e && e.message);
      return false;
    }
  }

  function initOne(ta){
    if (!isEl(ta)) return;
    var cont = ensurePair(ta);
    if (cont.dataset.qReady==='1') return;

    var details = cont.closest && cont.closest('details');
    if (details && !details.open){
      var onToggle = function(){
        if (details.open && cont.dataset.qReady!=='1'){
          requestAnimationFrame(function(){ createQuill(ta, cont); });
        }
        details.removeEventListener('toggle', onToggle);
      };
      details.addEventListener('toggle', onToggle);
      return;
    }

    if (!createQuill(ta, cont)){
      requestAnimationFrame(function(){ createQuill(ta, cont); });
    }
  }

  function boot(){
    var root = document.getElementById('intranet-root');
    if (!root || !window.Quill) return;
    root.querySelectorAll('textarea.rte').forEach(initOne);

    root.addEventListener('submit', function(){
      root.querySelectorAll('.rte-quill').forEach(function(cont){
        var ta = cont.parentElement && cont.parentElement.querySelector('textarea.rte');
        var ed = cont.querySelector('.ql-editor');
        if (ta && ed) ta.value = ed.innerHTML;
      });
    }, true);
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(boot, 0); });
  } else {
    setTimeout(boot, 0);
  }
})();
