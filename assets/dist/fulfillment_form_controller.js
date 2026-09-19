import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.typeSelect = this.element.querySelector('[data-fulfillment-type-select]');
    if (!this.typeSelect) {
      return;
    }

    this.manualTypes = JSON.parse(this.typeSelect.dataset.fulfillmentManualTypes || '[]');
    this.settings = this.element.querySelectorAll('[data-fulfillment-settings-for]');
    this.role = this.element.querySelector('[data-fulfillment-role]');

    this.toggle = this._toggle.bind(this);
    this.typeSelect.addEventListener('change', this.toggle);
    this._toggle();
  }

  disconnect() {
    this.typeSelect?.removeEventListener('change', this.toggle);
  }

  _toggle() {
    const type = this.typeSelect.value;

    this.settings.forEach((settings) => {
      this._setVisible(settings, settings.dataset.fulfillmentSettingsFor === type);
    });

    if (this.role) {
      const manual = this.manualTypes.includes(type);
      this._setVisible(this.role, manual);
      const roleSelect = this.role.querySelector('select');
      if (roleSelect) {
        roleSelect.required = manual;
      }
    }
  }

  _setVisible(element, visible) {
    element.classList.toggle('d-none', !visible);
    element.querySelectorAll('input, select, textarea').forEach((input) => {
      input.disabled = !visible;
    });
  }
}
