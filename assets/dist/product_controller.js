import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['stage', 'thumb', 'lightbox', 'lightboxImage'];
  static values = { index: { type: Number, default: 0 } };

  show({ params: { index } }) {
    this.indexValue = index;
  }

  previous() {
    this.indexValue = (this.indexValue - 1 + this.thumbTargets.length) % this.thumbTargets.length;
  }

  next() {
    this.indexValue = (this.indexValue + 1) % this.thumbTargets.length;
  }

  navigate(event) {
    if (this.thumbTargets.length < 2) {
      return;
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      this.previous();
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      this.next();
    }
  }

  indexValueChanged(index, previousIndex) {
    const thumb = this.thumbTargets[index];
    if (!thumb || !this.hasStageTarget) {
      return;
    }

    this.thumbTargets.forEach((t, i) => t.setAttribute('aria-current', i === index ? 'true' : 'false'));
    if (previousIndex === undefined || index === previousIndex) {
      return;
    }

    this.stageTarget.src = thumb.dataset.stageSrc;
    this.stageTarget.dataset.fullSrc = thumb.dataset.fullSrc;
    thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      this.stageTarget.animate([{ opacity: 0.35 }, { opacity: 1 }], { duration: 200, easing: 'ease-out' });
    }
  }

  enlarge() {
    this.lightboxImageTarget.src = this.stageTarget.dataset.fullSrc;
    this.lightboxTarget.showModal();
  }

  closeLightbox() {
    this.lightboxTarget.close();
  }

  selectPrice({ target }) {
    const tier = target.closest('[data-tier]');
    tier.querySelectorAll('[data-price]').forEach((amount) => {
      amount.hidden = amount.dataset.price !== target.value;
    });
  }
}
