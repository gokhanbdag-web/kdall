(() => {
    const list = document.getElementById('orderItems');
    const template = document.getElementById('orderItemTemplate');
    const add = document.getElementById('addOrderItem');
    if (!list || !template || !add) return;

    const bindRow = row => {
        const select = row.querySelector('.order-product');
        const price = row.querySelector('input[name="unit_price[]"]');
        const remove = row.querySelector('.remove-order-item');
        select?.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (option && option.dataset.price) price.value = option.dataset.price;
        });
        remove?.addEventListener('click', () => {
            if (list.querySelectorAll('.order-item-row').length > 1) row.remove();
        });
    };
    list.querySelectorAll('.order-item-row').forEach(bindRow);
    add.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        list.appendChild(row);
        bindRow(row);
    });
})();
