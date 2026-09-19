import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.typeSelect = this.element.querySelector('[data-price-type]');
    this.recurringFields = this.element.querySelector('[data-price-recurring]');
    if (!this.typeSelect || !this.recurringFields) {
      return;
    }

    this.intervalCount = this.recurringFields.querySelector('input');
    this.billingInterval = this.recurringFields.querySelector('select');
    this.editableInputs = [this.intervalCount, this.billingInterval].filter((input) => input && !input.disabled);

    this.toggle = this._toggle.bind(this);
    this.typeSelect.addEventListener('change', this.toggle);
    this._toggle();
  }

  disconnect() {
    this.typeSelect?.removeEventListener('change', this.toggle);
  }

  _toggle() {
    const recurring = this.typeSelect.value === 'recurring';
    this.recurringFields.classList.toggle('d-none', !recurring);

    this.editableInputs.forEach((input) => {
      input.disabled = !recurring;
      input.required = recurring;
    });

    if (recurring && this.editableInputs.length > 0) {
      this._setDefault(this.intervalCount, '1');
      this._setDefault(this.billingInterval, 'month');
    }
  }

  _setDefault(input, value) {
    if (input && !input.value) {
      input.value = value;
    }
  }
}
