//   1) Login/Signup: toggle password visibility 
document.querySelectorAll('.toggle-password').forEach(icon => {
  icon.addEventListener('click', function () {
    const input = this.previousElementSibling;
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      this.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
      input.type = 'password';
      this.innerHTML = '<i class="fas fa-eye"></i>';
    }
  });
});



// Import modal (single-step: choose -> preview/import)

(function(){
  var modal = document.getElementById('importModal');
  if (!modal) return;

  var closeBtn   = document.getElementById('close-import');   
  var chooseBtn  = document.getElementById('btn-import-choose');
  var fileInput  = document.getElementById('import-file');
  var fileBadge  = document.getElementById('import-chosen-file');
  var previewBtn = document.getElementById('btn-preview');
  var openBtn    = document.getElementById('open-import');

  function openModal(){
    // wipe any server-rendered results when reopening
    modal.querySelectorAll('.import-result').forEach(function(el){ el.remove(); });

    // reset file selection
    if (fileInput) fileInput.value = '';
    if (fileBadge) fileBadge.textContent = 'No file chosen';
    if (previewBtn){ previewBtn.hidden = true; previewBtn.disabled = true; }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
  }

  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
  }

  if (openBtn)   openBtn.addEventListener('click', openModal);
  if (closeBtn)  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

  if (chooseBtn) chooseBtn.addEventListener('click', function(){ if (fileInput) fileInput.click(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    var f = fileInput.files && fileInput.files[0];
    if (fileBadge) fileBadge.textContent = f ? ('Selected: ' + f.name) : 'No file chosen';
    if (previewBtn){ previewBtn.hidden = !f; previewBtn.disabled = !f; }
  });

  // If the server rendered preview/import result, keep the modal open.
  if (modal.classList.contains('is-open') && modal.querySelector('.import-result')) {
    modal.setAttribute('aria-hidden','false');
  }
})();


//  "Plan Next Year" dropdown (current-year page)
(function(){
  var btn  = document.getElementById('btn-plan-next');
  var menu = document.getElementById('menu-plan-next');
  if (!btn || !menu) return;

  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var shown = menu.style.display === 'block';
    menu.style.display = shown ? 'none' : 'block';
  });

  document.addEventListener('click', function(e){
    if (!menu.contains(e.target) && e.target !== btn) menu.style.display = 'none';
  });
})();


//    "Create for next year" small modal and auto-open import
(function(){
  var btnCreate = document.getElementById('btn-create-next');      // menu: "Create for next year"
  var modal     = document.getElementById('createNextModal');      // small confirm modal
  var closeBtn  = document.getElementById('close-create-next');
  var goImport  = document.getElementById('goto-next-and-import'); // open next page + open Import

  if (!modal) return;

  function open(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  function close(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }

  if (btnCreate) btnCreate.addEventListener('click', function(e){
    // if visually disabled, block again here
    if (btnCreate.classList.contains('is-disabled') || btnCreate.getAttribute('aria-disabled') === 'true') {
      e.preventDefault(); e.stopPropagation(); return false;
    }
    open();
  });
  if (closeBtn) closeBtn.addEventListener('click', close);
  if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) close(); });

  if (goImport){
    goImport.addEventListener('click', function(){
      var url = new URL(goImport.href, location.origin);
      url.searchParams.set('open_import', '1');
      goImport.href = url.toString();
    });
  }

  (function autoOpenOnNext(){
    var params = new URLSearchParams(location.search);
    if (params.get('open_import') === '1'){
      var openBtn = document.getElementById('open-import');
      if (openBtn) openBtn.click();
    }
  })();
})();

// Program → Term dependent selects
(function(){
  function bindRow(row){
    var prog = row.querySelector('.js-program');
    var term = row.querySelector('.js-term');
    if (!prog || !term) return;

    function filterTerms() {
      var p = prog.value;
      var anyVisible = false;
      var opts = term.querySelectorAll('option');
      var hasPlaceholder = opts.length && (opts[0].value === '' || opts[0].disabled);

      opts.forEach(function(opt, idx){
        if (hasPlaceholder && idx === 0) return; 
        var optProg = opt.getAttribute('data-program');
        var match = !p || (optProg === p);
        opt.hidden   = !match;
        opt.disabled = !match;
        if (match) anyVisible = true;
      });

      // Reset if current selection becomes hidden
      if (term.selectedIndex > 0 && term.options[term.selectedIndex].hidden) {
        term.selectedIndex = 0;
      }

      var note = term.querySelector('option[data-note="none"]');
      if (!anyVisible) {
        if (!note) {
          note = document.createElement('option');
          note.value = '';
          note.textContent = 'No terms for this program';
          note.disabled = true;
          note.selected = true;
          note.setAttribute('data-note','none');
          term.insertBefore(note, term.firstChild);
        }
      } else if (note) {
        note.remove();
      }
    }

    // Init + listen
    filterTerms();
    prog.addEventListener('change', filterTerms);
  }

  // Bind both the "new" row and any "edit" row
  document.querySelectorAll('.js-row').forEach(bindRow);
})();

// Enforce disabled state for Plan Next menu items
(function(){
  var menu = document.getElementById('menu-plan-next');
  if (!menu) return;

  menu.addEventListener('click', function(e){
    var el = e.target.closest('.menu-item');
    if (!el) return;

    var isDisabled = el.classList.contains('is-disabled') ||
                     el.getAttribute('aria-disabled') === 'true' ||
                     el.hasAttribute('disabled');
    if (isDisabled) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    if (el.id === 'btn-create-next') {
      var modal = document.getElementById('createNextModal');
      if (modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
      }
    }
  });

  var copyForm = document.getElementById('copyNextForm');
  if (copyForm) {
    copyForm.addEventListener('submit', function(e){
      var btn = copyForm.querySelector('button[type="submit"]');
      if (btn && (btn.classList.contains('is-disabled') || btn.disabled)) {
        e.preventDefault();
        return false;
      }
    });
  }
})();

// Generic "copy to clipboard" for buttons with data-copy-target

(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-copy-target]');
    if (!btn) return;
    var sel = btn.getAttribute('data-copy-target');
    var inp = document.querySelector(sel);
    if (!inp) return;

    try{
      inp.select(); inp.setSelectionRange(0, 99999);
      var ok = document.execCommand('copy');
      if (!ok && navigator.clipboard) {
        navigator.clipboard.writeText(inp.value || '');
      }
      btn.textContent = 'Copied';
      setTimeout(function(){ btn.textContent = 'Copy'; }, 1400);
    }catch(err){

    }
  });
})();
