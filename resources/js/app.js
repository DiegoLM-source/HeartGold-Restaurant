import './cliente/cliente.js';
import './cliente/qr.js';

const body = document.body;
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
const sidebarLinks = document.querySelectorAll('.worker-nav-link');

if (body.classList.contains('worker-body')) {
    const mobileQuery = window.matchMedia('(max-width: 760px)');

    const closeMobileSidebar = () => {
        body.classList.remove('mobile-sidebar-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    };

    sidebarToggle?.addEventListener('click', () => {
        if (mobileQuery.matches) {
            const isOpen = body.classList.toggle('mobile-sidebar-open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        } else {
            const isCollapsed = body.classList.toggle('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
            sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Expandir menú' : 'Contraer menú');
        }
    });

    sidebarCloseButtons.forEach((button) => button.addEventListener('click', closeMobileSidebar));

    sidebarLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) closeMobileSidebar();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMobileSidebar();
    });

    mobileQuery.addEventListener('change', () => {
        body.classList.remove('mobile-sidebar-open');
        if (!mobileQuery.matches) {
            body.classList.remove('sidebar-collapsed');
            sidebarToggle?.setAttribute('aria-expanded', 'true');
        }
    });
}

const tablesModal = document.querySelector('[data-table-modal]');

if (tablesModal) {
    const modalContent = tablesModal.querySelector('[data-modal-content]');
    const modalCloseButtons = tablesModal.querySelectorAll('[data-modal-close]');
    const modalError = tablesModal.querySelector('[data-modal-error]');
    const modalLoading = tablesModal.querySelector('[data-modal-loading]');

    const openTableModal = async (mesaId) => {
        tablesModal.classList.add('is-open');
        tablesModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        modalContent.innerHTML = '';
        modalError.textContent = '';
        modalLoading.hidden = false;

        try {
            const response = await fetch(
                `/trabajador/mesas/${encodeURIComponent(mesaId)}/detalle`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'No fue posible consultar la mesa.');
            }

            renderTableDetails(data);

        } catch (error) {
            modalError.textContent = error.message;
        } finally {
            modalLoading.hidden = true;
        }
    };

    const renderTableDetails = (data) => {
        const mesa = data.mesa;
        const sesion = data.sesion;
        const cliente = data.cliente;
        const pedidos = data.pedidos || [];

        const estado = mesa.estado || 'libre';

        const estadoClase = {
            libre: 'status-free',
            ocupada: 'status-occupied',
            reservada: 'status-reserved'
        }[estado] || 'status-inactive';

        const fecha = sesion?.iniciada_en
            ? new Date(sesion.iniciada_en).toLocaleString('es-CO')
            : 'Sin sesión activa';

        let pedidosHTML = '';

        if (pedidos.length === 0) {
            pedidosHTML = `
                <div class="modal-empty">
                    No hay pedidos registrados en esta sesión.
                </div>
            `;
        } else {
            pedidosHTML = pedidos.map((pedido, index) => {
                const productos = (pedido.productos || []).map(producto => `
                    <div class="modal-product">
                        <div>
                            <strong>${escapeModalText(producto.nombre_producto)}</strong>
                            <span>Cantidad: ${Number(producto.cantidad)}</span>
                        </div>
                        <strong>${formatMoney(producto.subtotal)}</strong>
                    </div>
                `).join('');

                return `
                    <div class="modal-order">
                        <div class="modal-order-heading">
                            <strong>Pedido ${pedidos.length - index}</strong>
                            <span class="order-state">${escapeModalText(pedido.estado)}</span>
                        </div>
                        ${productos}
                        ${pedido.observaciones ? `
                            <p class="modal-observation">
                                ${escapeModalText(pedido.observaciones)}
                            </p>
                        ` : ''}
                        <div class="modal-order-total">
                            Total: ${formatMoney(pedido.total)}
                        </div>
                    </div>
                `;
            }).join('');
        }

        modalContent.innerHTML = `
            <div class="modal-table-heading">
                <div>
                    <span class="modal-eyebrow">${escapeModalText(mesa.zona)}</span>
                    <h2>${escapeModalText(mesa.nombre)}</h2>
                    <p>${Number(mesa.capacidad)} personas · ${escapeModalText(mesa.forma)}</p>
                </div>
                <span class="table-status ${estadoClase}">
                    ${escapeModalText(estado)}
                </span>
            </div>

            <div class="modal-section">
                <h3>Información de la sesión</h3>
                ${sesion ? `
                    <div class="modal-info-grid">
                        <div class="modal-info-item">
                            <span>Estado</span>
                            <strong>Activa</strong>
                        </div>
                        <div class="modal-info-item">
                            <span>Inicio</span>
                            <strong>${escapeModalText(fecha)}</strong>
                        </div>
                        <div class="modal-info-item">
                            <span>Cliente asociado</span>
                            <strong>${escapeModalText(cliente?.full_name || 'Sin nombre registrado')}</strong>
                        </div>
                    </div>
                ` : `
                    <div class="modal-empty">
                        Esta mesa no tiene una sesión activa.
                    </div>
                `}
            </div>

            ${sesion ? `
                <div class="modal-section">
                    <h3>Pedidos de la sesión</h3>
                    <div class="modal-orders">
                        ${pedidosHTML}
                    </div>
                </div>

                <div class="modal-grand-total">
                    <span>Total de pedidos</span>
                    <strong>${formatMoney(data.total_pedidos)}</strong>
                </div>
            ` : ''}
        `;
    };

    const formatMoney = (value) => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(Number(value) || 0);
    };

    const escapeModalText = (value) => {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
    };

    const closeTableModal = () => {
        tablesModal.classList.remove('is-open');
        tablesModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };

    document.querySelectorAll('[data-table-id]').forEach((table) => {
        table.addEventListener('click', () => {
            openTableModal(table.dataset.tableId);
        });
    });

    modalCloseButtons.forEach((button) => {
        button.addEventListener('click', closeTableModal);
    });

    tablesModal.addEventListener('click', (event) => {
        if (event.target === tablesModal) {
            closeTableModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && tablesModal.classList.contains('is-open')) {
            closeTableModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.querySelector('.orders-workspace');

    if (!contenedor) return;

    const botones = contenedor.querySelectorAll('[data-order-filter]');
    const buscador = contenedor.querySelector('#order-search');
    const pedidos = contenedor.querySelectorAll('[data-order-card]');

    let estadoActual = 'todos';

    function filtrarPedidos() {
        const busqueda = buscador.value.trim().toLowerCase();

        pedidos.forEach((pedido) => {
            const estado = pedido.dataset.orderState;
            const texto = pedido.dataset.orderSearch || '';

            const coincideEstado =
                estadoActual === 'todos' || estado === estadoActual;

            const coincideBusqueda = texto.includes(busqueda);

            pedido.hidden = !(coincideEstado && coincideBusqueda);
        });

        const visibles = [...pedidos].filter((pedido) => !pedido.hidden);

        let mensaje = contenedor.querySelector('.orders-no-results');

        if (visibles.length === 0 && pedidos.length > 0) {
            if (!mensaje) {
                mensaje = document.createElement('div');
                mensaje.className = 'orders-no-results orders-empty';
                mensaje.innerHTML = `
                    <span class="orders-empty-icon">⌕</span>
                    <h3>No se encontraron pedidos</h3>
                    <p>Prueba con otro estado o término de búsqueda.</p>
                `;

                contenedor.querySelector('.orders-list')?.appendChild(mensaje);
            }
        } else if (mensaje) {
            mensaje.remove();
        }
    }

    botones.forEach((boton) => {
        boton.addEventListener('click', () => {
            estadoActual = boton.dataset.orderFilter;

            botones.forEach((item) => {
                item.classList.toggle('active', item === boton);
            });

            filtrarPedidos();
        });
    });

    buscador?.addEventListener('input', filtrarPedidos);
});

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('#reservations-container');

    if (!container) return;

    const filters = document.querySelectorAll('[data-reservation-filter]');
    const searchInput = document.querySelector('#reservation-search');
    const cards = container.querySelectorAll('[data-reservation-card]');

    let activeFilter = 'todos';

    function filterReservations() {
        const search = searchInput
            ? searchInput.value.trim().toLowerCase()
            : '';

        cards.forEach(card => {
            const state = card.dataset.reservationState;
            const text = card.dataset.reservationSearch || '';

            const matchesState =
                activeFilter === 'todos' || state === activeFilter;

            const matchesSearch = text.includes(search);

            card.hidden = !(matchesState && matchesSearch);
        });
    }

    filters.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.reservationFilter;

            filters.forEach(filter => {
                filter.classList.toggle('active', filter === button);
            });

            filterReservations();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterReservations);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const inventoryWorkspace = document.querySelector('.inventory-workspace');

    if (!inventoryWorkspace) return;

    const tabs = inventoryWorkspace.querySelectorAll('[data-inventory-tab]');
    const panels = inventoryWorkspace.querySelectorAll('[data-inventory-panel]');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const selectedSection = tab.dataset.inventoryTab;

            tabs.forEach(item => {
                item.classList.toggle('active', item === tab);
            });

            panels.forEach(panel => {
                const isSelected =
                    panel.dataset.inventoryPanel === selectedSection;

                panel.classList.toggle('active', isSelected);
                panel.hidden = !isSelected;
            });
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {

    const inventoryWorkspace = document.querySelector('.inventory-workspace');

    if (!inventoryWorkspace) return;

    const openButtons = inventoryWorkspace.querySelectorAll(
        '[data-open-inventory-modal]'
    );

    const closeButtons = inventoryWorkspace.querySelectorAll(
        '[data-close-inventory-modal]'
    );

    const modals = inventoryWorkspace.querySelectorAll('.inventory-modal');

    function openInventoryModal(modal) {
        if (!modal) return;

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
    }

    function closeInventoryModal(modal) {
        if (!modal) return;

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        const anotherModalOpen = Array.from(modals).some(
            item => !item.hidden
        );

        if (!anotherModalOpen) {
            document.body.style.overflow = '';
        }
    }

    openButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.dataset.openInventoryModal;
            const modal = document.getElementById(modalId);

            openInventoryModal(modal);
        });
    });

    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.inventory-modal');

            closeInventoryModal(modal);
        });
    });

    modals.forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                closeInventoryModal(modal);
            }
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            modals.forEach(modal => {
                if (!modal.hidden) {
                    closeInventoryModal(modal);
                }
            });
        }
    });

});

document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('.reservations-workspace');

    if (!workspace) return;

    const filters = workspace.querySelectorAll('[data-reservation-filter]');
    const searchInput = workspace.querySelector('#reservation-search');
    const cards = workspace.querySelectorAll('[data-reservation-card]');
    const emptyMessage = workspace.querySelector('.reservations-filter-empty');

    let activeFilter = 'todos';

    function filterReservations() {
        const search = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        cards.forEach(card => {
            const state = card.dataset.reservationState;
            const content = card.dataset.reservationSearch || '';

            const matchesFilter =
                activeFilter === 'todos' || state === activeFilter;

            const matchesSearch = content.includes(search);

            const visible = matchesFilter && matchesSearch;

            card.hidden = !visible;

            if (visible) {
                visibleCount++;
            }
        });

        if (emptyMessage) {
            emptyMessage.hidden = visibleCount > 0;
        }
    }

    filters.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.reservationFilter;

            filters.forEach(filter => {
                filter.classList.remove('active');
            });

            button.classList.add('active');

            filterReservations();
        });
    });

    searchInput?.addEventListener('input', filterReservations);

    filterReservations();
});

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const sidebar = document.querySelector('[data-sidebar]');
    const collapseButton = document.querySelector('[data-sidebar-collapse]');
    const toggleButton = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    if (!sidebar) return;

    collapseButton?.addEventListener('click', () => {
        body.classList.toggle('sidebar-collapsed');
    });

    toggleButton?.addEventListener('click', () => {
        body.classList.toggle('sidebar-open');
    });

    overlay?.addEventListener('click', () => {
        body.classList.remove('sidebar-open');
    });

    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            body.classList.remove('sidebar-open');
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            body.classList.remove('sidebar-open');
        }
    });
});

/* ==========================================
   ACTUALIZACION AUTOMATICA DE SOLICITUDES
   DE ACCESO A MESAS
========================================== */

document.addEventListener('DOMContentLoaded', () => {

    // Solo funciona en la página de mesas del trabajador.
    if (!document.querySelector('.mesa-requests-panel')) {
        return;
    }

    let actualizandoSolicitudes = false;
    let enviandoSolicitud = false;

    // Evita actualizar el panel mientras se acepta o rechaza
    // una solicitud.
    function escucharEnvioFormulario() {
        const panel = document.querySelector('.mesa-requests-panel');

        if (!panel) return;

        panel.addEventListener('submit', () => {
            enviandoSolicitud = true;
        });
    }

    escucharEnvioFormulario();

    async function actualizarSolicitudes() {

        if (
            actualizandoSolicitudes ||
            enviandoSolicitud ||
            document.hidden
        ) {
            return;
        }

        actualizandoSolicitudes = true;

        try {
            const respuesta = await fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!respuesta.ok) {
                throw new Error('No se pudieron actualizar las solicitudes.');
            }

            const html = await respuesta.text();

            const documento = new DOMParser().parseFromString(
                html,
                'text/html'
            );

            const panelActualizado = documento.querySelector(
                '.mesa-requests-panel'
            );

            const panelActual = document.querySelector(
                '.mesa-requests-panel'
            );

            if (panelActualizado && panelActual) {

                panelActual.replaceWith(panelActualizado);

                // Reactivar la escucha de formularios
                // después de reemplazar el panel.
                escucharEnvioFormulario();
            }

        } catch (error) {
            console.error(
                'Error actualizando solicitudes de mesas:',
                error
            );

        } finally {
            actualizandoSolicitudes = false;
        }
    }

    // Consultar nuevas solicitudes cada 10 segundos.
    setInterval(actualizarSolicitudes, 10000);

});
