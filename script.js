/* =========================================================
   Password eye toggle on auth forms
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
   Courses table: click any non-action cell to enter edit mode
   ========================================================= */
(() => {
  const tbl = document.getElementById('editableTable');
  if (!tbl) return;
  tbl.addEventListener('click', (ev) => {
    const td = ev.target.closest('td');
    const tr = ev.target.closest('tr');
    if (!td || !tr) return;
    if (tr.classList.contains('row-new')) return;   // skip the "new" row
    if (td.classList.contains('actions')) return;   // ignore action column
    const id = tr.dataset.id;
    if (!id) return;
    const url = new URL(location.href);
    url.searchParams.set('view','courses');
    url.searchParams.set('edit', id);
    location.href = url.toString();
  });
})();


/* =========================================================
   Import modal
   - Step 1: select year
   - Step 2: choose CSV → preview → (server renders Import)
   ========================================================= */
(() => {
  const modal = document.getElementById('importModal');
  if (!modal) return;

  // UI handles
  const openBtn    = document.getElementById('open-import');
  const closeBtn   = document.getElementById('close-import'); 
  const step1      = document.getElementById('import-step1');
  const step2      = document.getElementById('import-step2');
  const s1         = document.getElementById('stepper-1');
  const s2         = document.getElementById('stepper-2');

  const yearSelect = document.getElementById('importTargetYear');
  const hiddenYear = document.getElementById('importHiddenTarget');

  const chooseBtn  = document.getElementById('btn-import-choose');
  const fileInput  = document.getElementById('import-file');
  const fileBadge  = document.getElementById('import-chosen-file');
  const previewBtn = document.getElementById('btn-preview');

  // Helpers
  function goStep(n){
    step1.hidden = (n !== 1);
    step2.hidden = (n !== 2);
    s1.classList.toggle('active', n === 1);
    s2.classList.toggle('active', n === 2);
  }
  function wipeServerResults(){
    modal.querySelectorAll('.import-result').forEach(el => el.remove());
  }
  function resetStep2(){
    if (hiddenYear && yearSelect) hiddenYear.value = yearSelect.value;
    if (fileInput) fileInput.value = '';
    if (fileBadge) fileBadge.textContent = 'No file chosen';
    if (previewBtn){ previewBtn.hidden = true; previewBtn.disabled = true; }
  }
  function openModal(){
    wipeServerResults();
    resetStep2();
    goStep(1);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
  }

  // Open / close
  openBtn  && openBtn.addEventListener('click', openModal);
  closeBtn && closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // Keep hidden year in sync
  yearSelect && yearSelect.addEventListener('change', () => {
    if (hiddenYear) hiddenYear.value = yearSelect.value;
  });

  // Click "Choose CSV" → go step 2 and open file picker
  chooseBtn && chooseBtn.addEventListener('click', () => {
    if (hiddenYear && yearSelect) hiddenYear.value = yearSelect.value; // lock target year
    goStep(2);
    fileInput && fileInput.click();
  });

  // After selecting a file: show filename & enable Preview
  fileInput && fileInput.addEventListener('change', () => {
    const f = fileInput.files && fileInput.files[0];
    if (fileBadge) fileBadge.textContent = f ? ('Selected: ' + f.name) : 'No file chosen';
    if (previewBtn){ previewBtn.hidden = !f; previewBtn.disabled = !f; }
  });

  // If the server returned preview/import result, stay on Step 2 on first paint
  if (modal.classList.contains('is-open') && modal.querySelector('.import-result')) {
    goStep(2);
    if (hiddenYear && yearSelect && !hiddenYear.value) hiddenYear.value = yearSelect.value;
  } else {
    goStep(1);
  }
})();

