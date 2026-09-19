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

    this.amountInput = this.element.querySelector('[data-price-amount]');
    this.billingFields = this.element.querySelector('[data-price-billing]');
    this.typeBeforeFree = null;

    this.toggle = this._toggle.bind(this);
    this.toggleFree = this._toggleFree.bind(this);
    this.typeSelect.addEventListener('change', this.toggle);
    this.amountInput?.addEventListener('input', this.toggleFree);
    this._toggleFree();
  }

  disconnect() {
    this.typeSelect?.removeEventListener('change', this.toggle);
    this.amountInput?.removeEventListener('input', this.toggleFree);
  }

  _toggleFree() {
    if (!this.amountInput || !this.billingFields) {
      this._toggle();
      return;
    }

    const free = this._isFree();
    this.billingFields.classList.toggle('d-none', free);

    if (!this.typeSelect.disabled) {
      if (free && this.typeBeforeFree === null) {
        this.typeBeforeFree = this.typeSelect.value;
        this.typeSelect.value = 'one_time';
      } else if (!free && this.typeBeforeFree !== null) {
        this.typeSelect.value = this.typeBeforeFree;
        this.typeBeforeFree = null;
      }
    }

    this._toggle();
  }

  _isFree() {
    const value = this.amountInput.value.replace(/\s/g, '').replace(',', '.');
    return value !== '' && Number(value) === 0;
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
