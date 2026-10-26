const menu = document.querySelector('#mobile-menu');
const menuToggle = document.querySelector('.menu-toggle');
const gallery = document.querySelector('.gallery-dialog');
let galleryTrigger;
function syncModalState() {
  document.body.classList.toggle('modal-open', menu.open || gallery.open);
  menuToggle.setAttribute('aria-expanded', String(menu.open));
}
let menuAnimation = null;
let menuClosing = false;
function openMobileMenu() {
  if (menu.open) return;
  menuClosing = false;
  menu.classList.remove('is-closing');
  menu.showModal();
  syncModalState();
  if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
    menuAnimation = menu.animate(
      [{ transform: 'translateX(100%)' }, { transform: 'translateX(0)' }],
      { duration: 360, easing: 'cubic-bezier(.22,.7,.2,1)' }
    );
  }
}
function closeMobileMenu() {
  if (!menu.open || menuClosing) return;
  menuClosing = true;
  const start = getComputedStyle(menu).transform;
  menuAnimation?.cancel();
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) { menu.close(); return; }
  menu.classList.add('is-closing');
  menuAnimation = menu.animate(
    [{ transform: start === 'none' ? 'translateX(0)' : start }, { transform: 'translateX(100%)' }],
    { duration: 260, easing: 'cubic-bezier(.4,0,.8,.3)', fill: 'forwards' }
  );
  menuAnimation.onfinish = () => menu.close();
}
menuToggle.addEventListener('click', openMobileMenu);
document.querySelector('[data-close-menu]').addEventListener('click', closeMobileMenu);
menu.addEventListener('cancel', event => { event.preventDefault(); closeMobileMenu(); });
menu.addEventListener('close', () => {
  menuAnimation?.cancel(); menuAnimation = null; menuClosing = false;
  menu.classList.remove('is-closing');
  syncModalState(); menuToggle.focus({ preventScroll: true });
});
menu.addEventListener('click', event => {
  const rect = menu.getBoundingClientRect();
  if (event.target === menu && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) closeMobileMenu();
});
menu.querySelectorAll('nav a').forEach(link => link.addEventListener('click', closeMobileMenu));
const desktopSections = [...document.querySelectorAll('.desktop-section')];
const desktopDropdownAnimations = new WeakMap();
function setDesktopSection(section, open) {
  if ((section.dataset.open === 'true') === open) return;
  const panel = section.querySelector('.desktop-submenu');
  const currentClip = getComputedStyle(panel).clipPath;
  const currentTransform = getComputedStyle(panel).transform;
  const wasHidden = panel.hidden;
  desktopDropdownAnimations.get(section)?.cancel();
  section.dataset.open = String(open);
  section.querySelector('.desktop-section-link').setAttribute('aria-expanded', String(open));
  panel.inert = !open;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
    panel.hidden = !open;
    return;
  }
  panel.hidden = false;
  const collapsed = { clipPath: 'inset(0 0 100% 0)', transform: 'translateY(-8px)' };
  const expanded = { clipPath: 'inset(0 0 0% 0)', transform: 'translateY(0)' };
  const from = wasHidden ? collapsed : { clipPath: currentClip === 'none' ? expanded.clipPath : currentClip, transform: currentTransform };
  const animation = panel.animate([from, open ? expanded : collapsed], {
    duration: open ? 300 : 180, easing: 'cubic-bezier(.22,.7,.2,1)', fill: 'both'
  });
  desktopDropdownAnimations.set(section, animation);
  animation.onfinish = () => {
    if (desktopDropdownAnimations.get(section) !== animation) return;
    panel.hidden = !open;
    animation.cancel();
    desktopDropdownAnimations.delete(section);
  };
}
desktopSections.forEach(section => {
  let leaveTimer;
  const open = () => {
    clearTimeout(leaveTimer);
    desktopSections.forEach(other => setDesktopSection(other, other === section));
  };
  section.addEventListener('pointerenter', open);
  section.addEventListener('pointerleave', () => {
    leaveTimer = setTimeout(() => {
      if (!section.contains(document.activeElement)) setDesktopSection(section, false);
    }, 140);
  });
  section.addEventListener('focusin', open);
  section.addEventListener('focusout', event => {
    if (!section.contains(event.relatedTarget)) setDesktopSection(section, false);
  });
});
document.addEventListener('click', event => {
  if (!event.target.closest('.desktop-nav')) desktopSections.forEach(section => setDesktopSection(section, false));
});
document.addEventListener('keydown', event => {
  if (event.key !== 'Escape' || menu.open || gallery.open) return;
  const opened = desktopSections.find(section => section.dataset.open === 'true');
  if (opened) {
    opened.querySelector('.desktop-section-link').focus();
    setDesktopSection(opened, false);
  }
});
matchMedia('(min-width: 1100px)').addEventListener('change', event => { if (event.matches && menu.open) menu.close(); });
const tabs = [...document.querySelectorAll('.group-tabs [role="tab"]')];
function selectTab(tab, focus = false) {
  tabs.forEach(item => {
    const selected = item === tab;
    item.setAttribute('aria-selected', String(selected));
    item.tabIndex = selected ? 0 : -1;
    const panel = document.getElementById(item.getAttribute('aria-controls'));
    panel.hidden = !selected;
    panel.inert = !selected;
    panel.setAttribute('aria-hidden', String(!selected));
  });
  if (focus) tab.focus();
}
tabs.forEach((tab, index) => {
  tab.addEventListener('click', () => selectTab(tab));
  tab.addEventListener('keydown', event => {
    let target;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') target = tabs[(index + 1) % tabs.length];
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') target = tabs[(index + tabs.length - 1) % tabs.length];
    if (event.key === 'Home') target = tabs[0];
    if (event.key === 'End') target = tabs.at(-1);
    if (target) { event.preventDefault(); selectTab(target, true); }
  });
});
const groupTabList = document.querySelector('.group-tabs');
const stackedGroupTabs = matchMedia('(min-width: 1024px), (max-width: 359px)');
function syncGroupTabOrientation() {
  groupTabList?.setAttribute('aria-orientation', stackedGroupTabs.matches ? 'vertical' : 'horizontal');
}
syncGroupTabOrientation();
stackedGroupTabs.addEventListener('change', syncGroupTabOrientation);
const galleryPhotos = [...document.querySelectorAll('#gym-slider [data-gallery], [data-gallery-page] [data-gallery]')];
let galleryIndex = -1;
function showGalleryPhoto(trigger) {
  const caption = trigger.dataset.caption;
  gallery.querySelector('img').src = trigger.dataset.gallery;
  gallery.querySelector('img').alt = caption;
  gallery.querySelector('figcaption').textContent = caption;
  galleryIndex = galleryPhotos.findIndex(photo => photo.dataset.gallery === trigger.dataset.gallery);
  gallery.querySelector('.gallery-view-controls').hidden = galleryIndex < 0;
  gallery.querySelector('[data-gallery-counter]').textContent = `${String(galleryIndex + 1).padStart(2, '0')} / ${String(galleryPhotos.length).padStart(2, '0')}`;
}
function moveGalleryPhoto(direction) {
  if (galleryIndex >= 0) showGalleryPhoto(galleryPhotos[(galleryIndex + direction + galleryPhotos.length) % galleryPhotos.length]);
}
document.addEventListener('click', event => {
  const trigger = event.target.closest('[data-gallery]');
  if (!trigger || event.defaultPrevented) return;
  galleryTrigger = trigger.hasAttribute('data-gym-clone') ? trigger.closest('#gym-slider') : trigger;
  showGalleryPhoto(trigger);
  gallery.showModal(); syncModalState();
});
gallery.querySelector('[data-gallery-prev]').addEventListener('click', () => moveGalleryPhoto(-1));
gallery.querySelector('[data-gallery-next]').addEventListener('click', () => moveGalleryPhoto(1));
gallery.addEventListener('keydown', event => {
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault();
    moveGalleryPhoto(event.key === 'ArrowRight' ? 1 : -1);
  }
});
document.querySelector('[data-close-gallery]').addEventListener('click', () => gallery.close());
gallery.addEventListener('close', () => { syncModalState(); galleryTrigger?.focus({preventScroll:true}); });
for (const dialog of [gallery]) {
  dialog.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
  });
}

const gymTrack = document.querySelector('#gym-slider');
if (gymTrack) {
  const section = gymTrack.closest('.gym-section');
  const slides = [...gymTrack.querySelectorAll('.gym-slide')];
  const previous = section.querySelector('[data-gym-prev]');
  const next = section.querySelector('[data-gym-next]');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let gesture;
  let suppressPhotoClick = false;
  let slideOffsets = [];
  let loopStart = 0;
  let cycle = 0;
  let cloneSets = 0;
  let position = 0;
  let layoutWidth = 0;
  function rememberPosition() {
    if (gymTrack.clientWidth === layoutWidth && cycle) position = (gymTrack.scrollLeft - loopStart) / cycle;
  }
  function layoutLoop() {
    const firstLeft = slides[0].getBoundingClientRect().left;
    slideOffsets = slides.map(slide => slide.getBoundingClientRect().left - firstLeft);
    cycle = slideOffsets.at(-1) + slides.at(-1).getBoundingClientRect().width + parseFloat(getComputedStyle(gymTrack).gap);
    // Keep enough photographs on both sides to fill even a wide viewport.
    const sets = Math.max(1, Math.ceil(gymTrack.clientWidth / cycle));
    if (sets !== cloneSets) {
      gymTrack.querySelectorAll('[data-gym-clone]').forEach(clone => clone.remove());
      const before = document.createDocumentFragment();
      const after = document.createDocumentFragment();
      for (let set = 0; set < sets; set++) {
        for (const slide of slides) {
          for (const fragment of [before, after]) {
            const clone = slide.cloneNode(true);
            clone.setAttribute('data-gym-clone', '');
            clone.setAttribute('aria-hidden', 'true');
            clone.tabIndex = -1;
            fragment.append(clone);
          }
        }
      }
      gymTrack.prepend(before);
      gymTrack.append(after);
      cloneSets = sets;
    }
    const inset = parseFloat(getComputedStyle(gymTrack).paddingLeft);
    loopStart = slides[0].getBoundingClientRect().left - gymTrack.getBoundingClientRect().left + gymTrack.scrollLeft - inset;
    const progress = ((position % 1) + 1) % 1;
    layoutWidth = gymTrack.clientWidth;
    gymTrack.scrollTo({left:loopStart + progress * cycle, behavior:'instant'});
    rememberPosition();
  }
  function normalizeLoop() {
    const cycles = Math.floor((gymTrack.scrollLeft - loopStart + 1) / cycle);
    if (!cycles) return;
    const before = gymTrack.scrollLeft;
    // Rebase onto identical photographs after scrolling, without a visible jump.
    gymTrack.scrollTo({left:before - cycles * cycle, behavior:'instant'});
    if (gesture) gesture.start += gymTrack.scrollLeft - before;
  }
  function moveGym(direction) {
    normalizeLoop();
    const offset = gymTrack.scrollLeft - loopStart;
    const nearest = slideOffsets.reduce((best, value, index) => Math.abs(value - offset) < Math.abs(slideOffsets[best] - offset) ? index : best, 0);
    const target = nearest + direction;
    const index = ((target % slides.length) + slides.length) % slides.length;
    const lap = Math.floor(target / slides.length);
    gymTrack.scrollTo({left:loopStart + slideOffsets[index] + lap * cycle, behavior:reducedMotion.matches ? 'instant' : 'smooth'});
  }
  previous.addEventListener('click', () => moveGym(-1));
  next.addEventListener('click', () => moveGym(1));
  previous.disabled = false;
  next.disabled = false;
  layoutLoop();
  new ResizeObserver(layoutLoop).observe(gymTrack);
  gymTrack.addEventListener('scroll', rememberPosition, {passive:true});
  if ('onscrollend' in gymTrack) {
    gymTrack.addEventListener('scrollend', normalizeLoop);
  } else {
    let scrollTimer;
    gymTrack.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(normalizeLoop, 180);
    }, {passive:true});
  }
  gymTrack.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      moveGym(event.key === 'ArrowRight' ? 1 : -1);
    }
  });
  // Touch uses native scrolling with momentum; mouse dragging follows the pointer.
  gymTrack.addEventListener('pointerdown', event => {
    suppressPhotoClick = false;
    if (event.pointerType !== 'mouse' || event.button !== 0) return;
    event.preventDefault();
    gesture = {id:event.pointerId, x:event.clientX, start:gymTrack.scrollLeft, dragging:false};
  });
  gymTrack.addEventListener('pointermove', event => {
    if (!gesture || event.pointerId !== gesture.id) return;
    const delta = event.clientX - gesture.x;
    if (!gesture.dragging) {
      if (Math.abs(delta) < 6) return;
      gesture.dragging = true;
      suppressPhotoClick = true;
      gymTrack.classList.add('is-dragging');
      gymTrack.setPointerCapture(event.pointerId);
    }
    gymTrack.scrollLeft = gesture.start - delta;
    normalizeLoop();
  });
  function endGesture(event) {
    if (!gesture || gesture.id !== event.pointerId) return;
    const dragged = gesture.dragging;
    gesture = undefined;
    gymTrack.classList.remove('is-dragging');
    if (gymTrack.hasPointerCapture(event.pointerId)) gymTrack.releasePointerCapture(event.pointerId);
    if (dragged) moveGym(0);
  }
  window.addEventListener('pointerup', endGesture);
  gymTrack.addEventListener('pointercancel', endGesture);
  gymTrack.addEventListener('lostpointercapture', event => {
    if (event.target === gymTrack) endGesture(event);
  });
  gymTrack.addEventListener('click', event => {
    if (suppressPhotoClick) {
      event.preventDefault();
      event.stopPropagation();
      suppressPhotoClick = false;
    }
  }, true);
}

// Prepare only off-screen sections, so visible content never flashes back to zero opacity.
const sectionMotion = matchMedia('(prefers-reduced-motion: reduce)');
if (!sectionMotion.matches) {
  const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('section-reveal-visible');
      revealObserver.unobserve(entry.target);
    });
  }, { threshold: 0, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('#main > .section').forEach(section => {
    if (section.getBoundingClientRect().top < window.innerHeight) return;
    section.classList.add('section-reveal');
    revealObserver.observe(section);
    section.addEventListener('focusin', () => {
      section.classList.add('section-reveal-visible');
      revealObserver.unobserve(section);
    }, { once: true });
  });
  sectionMotion.addEventListener('change', event => {
    if (!event.matches) return;
    revealObserver.disconnect();
    document.querySelectorAll('.section-reveal').forEach(section => section.classList.add('section-reveal-visible'));
  });
}

// Mark the section currently passing through the upper part of the viewport.
const headerNavLinks = [...document.querySelectorAll('.desktop-nav a')];
const headerSectionObserver = new IntersectionObserver(entries => {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue;
    headerNavLinks.forEach(link => {
      if (link.hash === `#${entry.target.id}`) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  }
}, {rootMargin: '-10% 0px -80% 0px', threshold: 0});
document.querySelectorAll('main > section[id], #top').forEach(section => headerSectionObserver.observe(section));

document.querySelectorAll('[data-copyright-year]').forEach(element => {
  element.textContent = String(new Date().getFullYear());
});

// Footer groups start closed on small screens and remain expanded on desktop.
const footerMobile = matchMedia('(max-width: 767px)');
const footerColumns = [...document.querySelectorAll('.footer-column')];
const footerDisclosureState = new WeakMap();
footerColumns.forEach(column => {
  const summary = column.querySelector('summary');
  const content = column.querySelector('ul');
  const state = { expanded: column.open, animation: null };
  footerDisclosureState.set(column, state);
  summary.addEventListener('click', event => {
    if (event.target.closest('a')) return;
    if (!footerMobile.matches) return;
    event.preventDefault();
    const startHeight = column.getBoundingClientRect().height;
    state.animation?.cancel();
    state.expanded = !state.expanded;
    column.dataset.expanded = String(state.expanded);
    summary.setAttribute('aria-expanded', String(state.expanded));
    content.inert = !state.expanded;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
      column.open = state.expanded;
      column.style.removeProperty('overflow');
      return;
    }
    // Keep the content rendered until the closing animation finishes.
    column.open = true;
    const endHeight = state.expanded ? column.getBoundingClientRect().height
      : summary.getBoundingClientRect().height + parseFloat(getComputedStyle(column).borderBottomWidth);
    column.style.overflow = 'hidden';
    const animation = column.animate(
      [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
      { duration: 260, easing: 'cubic-bezier(.25,.8,.25,1)', fill: 'both' }
    );
    state.animation = animation;
    animation.onfinish = () => {
      if (state.animation !== animation) return;
      column.open = state.expanded;
      animation.cancel();
      state.animation = null;
      column.style.removeProperty('overflow');
    };
  });
});
function syncFooterColumns() {
  footerColumns.forEach(column => {
    const state = footerDisclosureState.get(column);
    state.animation?.cancel();
    state.animation = null;
    state.expanded = !footerMobile.matches;
    column.open = !footerMobile.matches;
    column.dataset.expanded = String(column.open);
    column.style.removeProperty('overflow');
    column.querySelector('ul').inert = false;
    column.querySelector('summary').setAttribute('aria-expanded', String(column.open));
    column.querySelector('summary').tabIndex = footerMobile.matches ? 0 : -1;
  });
}
syncFooterColumns();
footerMobile.addEventListener('change', syncFooterColumns);
document.querySelectorAll('[data-footer-placeholder]').forEach(link => {
  link.addEventListener('click', event => event.preventDefault());
});

// Animate FAQ height in both directions, including rapid reversals.
document.querySelectorAll('.faq-list details, .mobile-section').forEach(details => {
  const summary = details.querySelector('summary');
  const answer = details.querySelector('.faq-answer, .mobile-submenu');
  let expanded = details.open;
  let animation = null;
  summary.setAttribute('aria-expanded', String(expanded));
  summary.addEventListener('click', event => {
    if (event.target.closest('a')) return;
    event.preventDefault();
    const startHeight = details.getBoundingClientRect().height;
    animation?.cancel();
    expanded = !expanded;
    details.dataset.expanded = String(expanded);
    summary.setAttribute('aria-expanded', String(expanded));
    answer.inert = !expanded;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
      details.open = expanded;
      details.style.removeProperty('overflow');
      return;
    }
    details.open = true;
    const endHeight = expanded ? details.getBoundingClientRect().height
      : summary.getBoundingClientRect().height + parseFloat(getComputedStyle(details).borderBottomWidth);
    details.style.overflow = 'hidden';
    const current = details.animate(
      [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
      { duration: 260, easing: 'cubic-bezier(.25,.8,.25,1)', fill: 'both' }
    );
    animation = current;
    current.onfinish = () => {
      if (animation !== current) return;
      details.open = expanded;
      current.cancel();
      animation = null;
      details.style.removeProperty('overflow');
    };
  });
});

const scrollToTop = document.querySelector('.scroll-to-top');
if (scrollToTop) {
  function syncScrollToTop() {
    scrollToTop.hidden = window.scrollY < 400;
  }
  window.addEventListener('scroll', syncScrollToTop, {passive: true});
  syncScrollToTop();
  scrollToTop.addEventListener('click', () => {
    window.scrollTo({top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
  });
}
// Team carousel: swipe, mouse drag, keyboard and previous/next controls.
const trainersTrack = document.querySelector('#trainers-slider');
if (trainersTrack) {
  const cards = [...trainersTrack.querySelectorAll('.trainer-card')];
  const previous = document.querySelector('[data-trainers-prev]');
  const next = document.querySelector('[data-trainers-next]');
  const dots = [...document.querySelectorAll('[data-trainer-dot]')];
  const dotGroup = document.querySelector('.trainers-dots');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  let drag = null;
  let suppressClick = false;
  function syncTrainerControls() {
    const scrollable = trainersTrack.scrollWidth > trainersTrack.clientWidth + 2;
    trainersTrack.dataset.scrollable = String(scrollable);
    trainersTrack.tabIndex = scrollable ? 0 : -1;
    previous.parentElement.hidden = !scrollable;
    dotGroup.hidden = !scrollable;
    const trackLeft = trainersTrack.getBoundingClientRect().left;
    const nearest = cards.reduce((best, card, index) => Math.abs(card.getBoundingClientRect().left - trackLeft) < Math.abs(cards[best].getBoundingClientRect().left - trackLeft) ? index : best, 0);
    dots.forEach((dot, index) => {
      if (index === nearest) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
    previous.disabled = trainersTrack.scrollLeft <= 2;
    next.disabled = trainersTrack.scrollLeft >= trainersTrack.scrollWidth - trainersTrack.clientWidth - 2;
  }
  function moveTrainer(direction) {
    if (trainersTrack.dataset.scrollable !== 'true') return;
    const trackLeft = trainersTrack.getBoundingClientRect().left;
    const offsets = cards.map(card => card.getBoundingClientRect().left - trackLeft + trainersTrack.scrollLeft);
    const current = offsets.reduce((best, offset, i) => Math.abs(offset - trainersTrack.scrollLeft) < Math.abs(offsets[best] - trainersTrack.scrollLeft) ? i : best, 0);
    const index = Math.max(0, Math.min(cards.length - 1, current + direction));
    trainersTrack.scrollTo({left: offsets[index], behavior: reduced.matches ? 'instant' : 'smooth'});
  }
  previous.addEventListener('click', () => moveTrainer(-1));
  next.addEventListener('click', () => moveTrainer(1));
  dots.forEach((dot, index) => dot.addEventListener('click', () => {
    const offset = cards[index].getBoundingClientRect().left - trainersTrack.getBoundingClientRect().left + trainersTrack.scrollLeft;
    trainersTrack.scrollTo({left:offset, behavior:reduced.matches ? 'instant' : 'smooth'});
  }));
  trainersTrack.addEventListener('scroll', syncTrainerControls, {passive:true});
  new ResizeObserver(syncTrainerControls).observe(trainersTrack);
  syncTrainerControls();
  trainersTrack.addEventListener('keydown', event => {
    if (trainersTrack.dataset.scrollable !== 'true') return;
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    event.preventDefault(); moveTrainer(event.key === 'ArrowRight' ? 1 : -1);
  });
  trainersTrack.addEventListener('dragstart', event => event.preventDefault());
  trainersTrack.addEventListener('pointerdown', event => {
    suppressClick = false;
    if (trainersTrack.dataset.scrollable !== 'true') return;
    if (event.pointerType !== 'mouse' || event.button !== 0) return;
    drag = {id:event.pointerId, x:event.clientX, start:trainersTrack.scrollLeft, active:false};
  });
  trainersTrack.addEventListener('pointermove', event => {
    if (!drag || drag.id !== event.pointerId) return;
    const delta = event.clientX - drag.x;
    if (!drag.active) {
      if (Math.abs(delta) < 6) return;
      drag.active = true; suppressClick = true;
      trainersTrack.classList.add('is-dragging');
      trainersTrack.setPointerCapture(event.pointerId);
    }
    trainersTrack.scrollLeft = drag.start - delta;
  });
  function endTrainerDrag(event) {
    if (!drag || drag.id !== event.pointerId) return;
    drag = null;
    trainersTrack.classList.remove('is-dragging');
    if (trainersTrack.hasPointerCapture(event.pointerId)) trainersTrack.releasePointerCapture(event.pointerId);
  }
  window.addEventListener('pointerup', endTrainerDrag);
  trainersTrack.addEventListener('pointercancel', endTrainerDrag);
  trainersTrack.addEventListener('lostpointercapture', endTrainerDrag);
  trainersTrack.addEventListener('click', event => {
    if (!suppressClick) return;
    event.preventDefault(); event.stopPropagation(); suppressClick = false;
  }, true);
}
