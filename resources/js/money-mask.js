// Formatação monetária em tempo real, antes de o Livewire capturar o input.
const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

document.addEventListener('input', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !input.matches('[data-money-mask]')) {
        return;
    }

    // Entrada de trás para frente: 1 -> R$ 0,01; 123456 -> R$ 1.234,56.
    const digits = input.value.replace(/\D/g, '').slice(0, 11);
    const amount = Number(digits || '0') / 100;
    const formatted = 'R$ ' + currencyFormatter.format(amount);

    input.value = formatted;
    input.setSelectionRange(formatted.length, formatted.length);
}, true);
