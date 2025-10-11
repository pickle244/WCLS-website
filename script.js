/* =========================================================
   1) Login/Signup: toggle password visibility
   ========================================================= */
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


/* =========================================================
   2) Courses: NO row-click editing
   ---------------------------------------------------------
   We intentionally DO NOT attach any click handler on table
   rows or cells. Editing should ONLY be triggered by the
   explicit "Edit" button/link in the last column.
   ========================================================= */
// (Intentionally left blank to avoid row-click-to-edit behavior)


/* =========================================================
   3) Import modal (single-step: choose file -> preview/import)
   ========================================================= */
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


/* =========================================================
   4) "Plan Next Year" dropdown (current-year page)
   ========================================================= */
(function(){
  var btn = document.getElementById('btn-plan-next');
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


/* =========================================================
   5) "Create for next year" small modal and auto-open import
   ========================================================= */
(function(){
  var btnCreate = document.getElementById('btn-create-next');      // "Plan Next Year" → "Create for next year"
  var modal     = document.getElementById('createNextModal');      // Small confirmation modal in index.php
  var closeBtn  = document.getElementById('close-create-next');
  var goImport  = document.getElementById('goto-next-and-import'); // Link to next-year page (and open Import)

  if (!btnCreate || !modal) return; // Not on the courses_current page

  function open(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  function close(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }

  btnCreate.addEventListener('click', open);
  if (closeBtn) closeBtn.addEventListener('click', close);
  modal.addEventListener('click', function(e){ if (e.target === modal) close(); });

  // Add a query flag so the next page will auto-open the Import modal
  if (goImport){
    goImport.addEventListener('click', function(){
      var url = new URL(goImport.href, location.origin);
      url.searchParams.set('open_import', '1');
      goImport.href = url.toString();
    });
  }

  // If we land on the next-year page with ?open_import=1, programmatically click the "Import" button
  (function autoOpenOnNext(){
    var params = new URLSearchParams(location.search);
    if (params.get('open_import') === '1'){
      var openBtn = document.getElementById('open-import');
      if (openBtn) openBtn.click();
    }
  })();
})();


/* =========================================================
   6) Program → Term dependent selects (bind per row)
   ---------------------------------------------------------
   Each row has two selects:
     - .js-program (Program)
     - .js-term    (Term)
   We filter <option> of term by data-program attribute.
   ========================================================= */
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
        if (hasPlaceholder && idx === 0) return; // keep the placeholder
        var optProg = opt.getAttribute('data-program');
        var match = !p || (optProg === p);
        opt.hidden   = !match;
        opt.disabled = !match;
        if (match) anyVisible = true;
      });

      // Reset selection if the currently selected option is filtered out
      if (term.selectedIndex > 0 && term.options[term.selectedIndex].hidden) {
        term.selectedIndex = 0;
      }

      // When no option is available, show a disabled note option
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

    // Initial filter + listen to program changes
    filterTerms();
    prog.addEventListener('change', filterTerms);
  }

  // Bind all rows (new row + edit row)
  document.querySelectorAll('.js-row').forEach(bindRow);
})();
