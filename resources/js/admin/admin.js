document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('[data-recipe-lines]');
    const addButton = document.querySelector('[data-add-recipe]');

    if (!list || !addButton) return;

    const nextIndex = () => {
        const indexes = [...list.querySelectorAll('[name^="receta["]')]
            .map((field) => Number(field.name.match(/^receta\[(\d+)\]/)?.[1] ?? -1));

        return Math.max(-1, ...indexes) + 1;
    };

    addButton.addEventListener('click', () => {
        const source = list.querySelector('[data-recipe-line]');
        if (!source) return;

        const row = source.cloneNode(true);
        const index = nextIndex();

        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/^receta\[\d+\]/, `receta[${index}]`);
            if (field.matches('select')) field.selectedIndex = 0;
            else field.value = '';
        });

        list.append(row);
        row.querySelector('select')?.focus();
    });

    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-recipe]');
        if (!removeButton) return;

        const row = removeButton.closest('[data-recipe-line]');
        if (list.querySelectorAll('[data-recipe-line]').length === 1) {
            row.querySelectorAll('select, input').forEach((field) => {
                if (field.matches('select')) field.selectedIndex = 0;
                else field.value = '';
            });
            return;
        }

        row.remove();
    });
});
