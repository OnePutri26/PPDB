document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-multistep]');
  const configNode = document.querySelector('#levelConfig');
  const levels = configNode ? JSON.parse(configNode.textContent || '[]') : [];

  if (form) {
    const steps = [...form.querySelectorAll('[data-step]')];
    const indicators = [...form.querySelectorAll('[data-step-indicator]')];
    const countLabel = form.querySelector('[data-step-count]');
    const backButton = form.querySelector('[data-step-back]');
    const nextButton = form.querySelector('[data-step-next]');
    const submitButton = form.querySelector('[data-step-submit]');
    const yearSelect = form.querySelector('#academic_year_id');
    const waveSelect = form.querySelector('#wave_id');
    const levelRadios = [...form.querySelectorAll('[name="education_level_id"]')];
    let active = 0;
    const validateCurrentStep = () => {
      const currentInputs = [...steps[active].querySelectorAll('input,select,textarea')];
      const invalid = currentInputs.find((field) => field.required && !field.reportValidity());
      return !invalid;
    };

    const selectedLevel = () => {
      const selected = levelRadios.find((radio) => radio.checked);
      return levels.find((level) => level.id === selected?.value) || null;
    };

    const setOptions = (select, options, placeholder, selectedValue = '') => {
      select.replaceChildren(new Option(placeholder, ''));
      options.forEach((item) => {
        const option = new Option(item.name, item.id);
        if (item.id === selectedValue) option.selected = true;
        select.add(option);
      });
    };

    const updateLevelFields = () => {
      const level = selectedLevel();
      const priorLabel = form.querySelector('[data-prior-label]');
      const schoolName = form.querySelector('#previous_school_name');
      const graduationYear = form.querySelector('#graduation_year');
      const priorSchoolRequired = Boolean(level && level.code !== 'SD');
      if (priorLabel) priorLabel.textContent = level?.priorSchool || 'Asal sekolah';
      if (schoolName) schoolName.required = priorSchoolRequired;
      if (graduationYear) graduationYear.required = priorSchoolRequired;

      const classWrapper = form.querySelector('[data-class-choice]');
      const classSelect = form.querySelector('#class_choice');
      const previousClass = classSelect.value;
      if (classWrapper && classSelect) {
        classWrapper.hidden = !level?.classes.length;
        setOptions(classSelect, (level?.classes || []).map((name, index) => ({ id: name, name })), 'Pilih kelas (opsional)', previousClass);
      }

      const programs = level?.majors || [];
      const isProgramLevel = ['SMA', 'SMK'].includes(level?.code);
      const primaryWrapper = form.querySelector('[data-primary-major]');
      const primarySelect = form.querySelector('#major_id');
      const primaryLabel = form.querySelector('[data-primary-major-label]');
      const previousMajor = primarySelect.value;
      if (primaryWrapper && primarySelect) {
        primaryWrapper.hidden = !isProgramLevel || programs.length === 0;
        setOptions(primarySelect, programs, 'Pilih program', previousMajor);
        primarySelect.required = level?.code === 'SMK' && programs.length > 0;
        if (primaryLabel) primaryLabel.textContent = level?.code === 'SMK' ? `${level.programLabel} utama *` : `${level.programLabel} (opsional)`;
      }

      const secondaryWrapper = form.querySelector('[data-second-major]');
      const secondarySelect = form.querySelector('#second_major_id');
      const previousSecondary = secondarySelect.value;
      if (secondaryWrapper && secondarySelect) {
        secondaryWrapper.hidden = level?.code !== 'SMK' || programs.length < 2;
        setOptions(secondarySelect, programs.filter((item) => item.id !== primarySelect?.value), 'Tidak memilih', previousSecondary);
      }

      form.querySelectorAll('[data-level-document]').forEach((input) => {
        const required = input.dataset.requiredFor.split(',').includes(level?.code || '');
        input.required = required && !input.dataset.alreadyUploaded;
        input.closest('[data-document-tile]').hidden = input.dataset.levels && !input.dataset.levels.split(',').includes(level?.code || '');
        const label = input.closest('[data-document-tile]').querySelector('[data-document-label]');
        if (label) {
          const base = label.textContent.replace(/\s*(\*|· opsional)$/, '');
          label.textContent = `${base}${required ? ' *' : ' · opsional'}`;
        }
      });

      const info = form.querySelector('[data-level-info]');
      if (info) {
        info.replaceChildren();
        [level?.tuition, level?.facilities].filter(Boolean).forEach((text) => {
          const item = document.createElement('div');
          item.className = 'alert alert-light';
          item.textContent = text;
          info.append(item);
        });
      }
    };

    const updateWaves = () => {
      if (!yearSelect || !waveSelect) return;
      [...waveSelect.options].forEach((option) => {
        if (option.value) option.hidden = option.dataset.year !== yearSelect.value;
      });
      if (waveSelect.selectedOptions[0]?.hidden) waveSelect.value = '';
    };

    const updateReview = () => {
      const target = form.querySelector('[data-review-summary]');
      if (!target) return;
      const labels = [
        ['Jenjang', () => selectedLevel()?.name],
        ['Tahun ajaran', () => yearSelect?.selectedOptions[0]?.textContent],
        ['Gelombang', () => waveSelect?.selectedOptions[0]?.textContent],
        ['Nama calon siswa', () => form.querySelector('#full_name')?.value],
        ['Email akun', () => document.querySelector('[data-user-email]')?.textContent || 'Tersimpan di akun'],
        ['Sekolah asal', () => form.querySelector('#previous_school_name')?.value || 'Tidak diisi'],
        ['Jalur pendaftaran', () => form.querySelector('#path_id')?.selectedOptions[0]?.textContent],
        ['Pilihan program', () => form.querySelector('#major_id')?.selectedOptions[0]?.textContent || 'Tidak dipilih'],
        ['WhatsApp orang tua / wali', () => form.querySelector('#parent_whatsapp')?.value],
      ];
      target.replaceChildren();
      labels.forEach(([label, getter]) => {
        const value = String(getter() || '').trim();
        if (!value) return;
        const column = document.createElement('div');
        column.className = 'col-md-6';
        const row = document.createElement('div');
        row.className = 'review-row';
        const labelNode = document.createElement('span');
        labelNode.textContent = label;
        const valueNode = document.createElement('span');
        valueNode.textContent = value;
        row.append(labelNode, valueNode);
        column.append(row);
        target.append(column);
      });
    };

    const show = () => {
      steps.forEach((step, index) => { step.hidden = index !== active; });
      indicators.forEach((item, index) => {
        item.classList.toggle('active', index === active);
        item.classList.toggle('complete', index < active);
        item.setAttribute('aria-current', index === active ? 'step' : 'false');
      });
      if (backButton) backButton.disabled = active === 0;
      if (nextButton) nextButton.hidden = active === steps.length - 1;
      if (submitButton) submitButton.hidden = active !== steps.length - 1;
      if (countLabel) countLabel.textContent = `Langkah ${active + 1} dari ${steps.length}`;
      if (active === 7) updateReview();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    form.addEventListener('change', (event) => {
      if (event.target.matches('[name="education_level_id"]')) updateLevelFields();
      if (event.target === yearSelect) updateWaves();
      if (event.target.matches('#major_id')) updateLevelFields();
    });

    indicators.forEach((indicator) => {
      indicator.addEventListener('click', () => {
        const target = Number(indicator.dataset.stepIndicator);
        if (target <= active) {
          active = target;
          show();
        } else if (target === active + 1 && validateCurrentStep()) {
          active = target;
          show();
        }
      });
    });

    if (yearSelect && waveSelect) {
      yearSelect.addEventListener('change', updateWaves);
      updateWaves();
    }

    if (nextButton) nextButton.addEventListener('click', () => {
      if (validateCurrentStep() && active < steps.length - 1) {
        active += 1;
        show();
      }
    });

    if (backButton) backButton.addEventListener('click', () => {
      if (active > 0) {
        active -= 1;
        show();
      }
    });

    form.addEventListener('submit', (event) => {
      const requiredInputs = [...steps[active].querySelectorAll('[required]')];
      const invalid = requiredInputs.find((field) => !field.reportValidity());
      if (invalid) {
        event.preventDefault();
        return;
      }
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Mengirim...';
      }
    });

    updateLevelFields();
    show();

    form.querySelectorAll('[data-upload-zone]').forEach((zone) => {
      const input = zone.querySelector('input[type="file"]');
      const preview = zone.querySelector('[data-file-preview]');
      if (!input || !preview) return;
      let previewUrl = '';

      const renderFile = () => {
        if (previewUrl) {
          URL.revokeObjectURL(previewUrl);
          previewUrl = '';
        }
        preview.replaceChildren();
        const file = input.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
          input.setCustomValidity('Ukuran file maksimal 5 MB.');
          input.value = '';
          preview.textContent = 'File terlalu besar — pilih file maksimal 5 MB.';
          return;
        }
        input.setCustomValidity('');
        const icon = document.createElement('i');
        icon.className = `fa-solid ${file.type.startsWith('image/') ? 'fa-image' : 'fa-file-pdf'}`;
        const name = document.createElement('span');
        name.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
        if (file.type.startsWith('image/')) {
          const image = document.createElement('img');
          previewUrl = URL.createObjectURL(file);
          image.src = previewUrl;
          image.alt = `Pratinjau ${file.name}`;
          image.className = 'file-preview-thumb';
          preview.append(image);
        }
        preview.append(icon, name);
      };

      input.addEventListener('change', renderFile);
      zone.addEventListener('dragover', (event) => {
        event.preventDefault();
        zone.classList.add('is-dragging');
      });
      zone.addEventListener('dragleave', (event) => {
        if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging');
      });
      zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragging');
        const file = event.dataTransfer?.files?.[0];
        if (!file || typeof DataTransfer === 'undefined') return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  document.querySelectorAll('form[data-loading]').forEach((loadingForm) => {
    loadingForm.addEventListener('submit', () => {
      const button = loadingForm.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memproses...';
      }
    });
  });

  document.querySelectorAll('[data-password-confirm]').forEach((confirmation) => {
    confirmation.addEventListener('input', () => {
      const password = document.querySelector(confirmation.dataset.passwordConfirm);
      confirmation.setCustomValidity(password && password.value !== confirmation.value ? 'Kata sandi belum sama.' : '');
    });
  });

  const revealItems = document.querySelectorAll('.feature-card, .level-card, .program-card, .step-card');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08 });
    revealItems.forEach((item) => {
      item.classList.add('scroll-reveal');
      observer.observe(item);
    });
  }

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.hero-actions .btn-primary, .navbar .nav-cta').forEach((button) => {
      button.addEventListener('pointermove', (event) => {
        const bounds = button.getBoundingClientRect();
        const x = (event.clientX - bounds.left) / bounds.width - .5;
        const y = (event.clientY - bounds.top) / bounds.height - .5;
        button.style.transform = `translate(${x * 3}px,${y * 2}px)`;
      });
      button.addEventListener('pointerleave', () => { button.style.transform = ''; });
    });
  }
});
