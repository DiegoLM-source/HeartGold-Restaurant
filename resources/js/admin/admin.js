document.addEventListener('DOMContentLoaded', () => {
    const tableForm = document.querySelector('[data-table-form]');

    if (tableForm) {
        const viewport = document.querySelector('[data-table-preview]');
        const canvas = document.querySelector('[data-table-preview-canvas]');
        const marker = document.querySelector('[data-table-preview-marker]');
        const fields = Object.fromEntries(['nombre', 'capacidad', 'forma', 'pos_x', 'pos_y', 'ancho', 'alto', 'rotacion']
            .map((name) => [name, tableForm.elements.namedItem(name)]));
        const number = (field, fallback) => {
            const value = Number.parseFloat(field?.value);
            return Number.isFinite(value) ? Math.max(0, value) : fallback;
        };

        const updatePreview = () => {
            const x = number(fields.pos_x, 0);
            const y = number(fields.pos_y, 0);
            const width = Math.max(45, number(fields.ancho, 100));
            const height = Math.max(45, number(fields.alto, 80));
            const canvasWidth = Math.max(canvas.clientWidth, viewport.clientWidth, x + width + 48);
            const canvasHeight = Math.max(canvas.clientHeight, 420, y + height + 48);

            canvas.style.width = `${canvasWidth}px`;
            canvas.style.height = `${canvasHeight}px`;
            marker.style.left = `${x}px`;
            marker.style.top = `${y}px`;
            marker.style.width = `${width}px`;
            marker.style.height = `${height}px`;
            marker.style.transform = `rotate(${Number.parseFloat(fields.rotacion?.value) || 0}deg)`;
            marker.classList.toggle('is-round', fields.forma?.value === 'circular');

            document.querySelector('[data-preview-name]').textContent = fields.nombre?.value.trim() || 'Mesa';
            document.querySelector('[data-preview-capacity]').textContent = fields.capacidad?.value || '0';
            document.querySelector('[data-preview-x]').textContent = x;
            document.querySelector('[data-preview-y]').textContent = y;

            viewport.scrollLeft = Math.max(0, x + width / 2 - viewport.clientWidth / 2);
            viewport.scrollTop = Math.max(0, y + height / 2 - viewport.clientHeight / 2);
        };

        Object.values(fields).forEach((field) => field?.addEventListener('input', updatePreview));
        Object.values(fields).forEach((field) => field?.addEventListener('change', updatePreview));
        updatePreview();
    }

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
