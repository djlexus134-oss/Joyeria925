<?php
$modalPiezaApiUrl = $modalPiezaApiUrl ?? 'tienda_pieza_api.php';
$modalCarritoApiUrl = $modalCarritoApiUrl ?? 'tienda_carrito_api.php';
?>
<div class="modal fade" id="modalPiezaDetalle" tabindex="-1" aria-hidden="true"
     data-api-url="<?php echo htmlspecialchars($modalPiezaApiUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-carrito-api-url="<?php echo htmlspecialchars($modalCarritoApiUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de la pieza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="modal-pieza-loading text-center py-5">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div>
                </div>
                <div class="alert alert-danger modal-pieza-error d-none" role="alert"></div>
                <div class="modal-pieza-contenido d-none">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="modal-pieza-img-principal-wrap" style="background:#f7f7f7;border:1px solid #eee;border-radius:8px;overflow:hidden;">
                                <img class="modal-pieza-img-principal w-100" src="" alt="" style="object-fit:cover;aspect-ratio:1/1;display:block;">
                            </div>
                            <div class="modal-pieza-img-thumbs d-flex flex-wrap gap-2 mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1 modal-pieza-familia"></p>
                            <h4 class="mb-2 modal-pieza-descripcion"></h4>
                            <div class="modal-pieza-precio fs-3 fw-bold mb-3"></div>

                            <span class="badge rounded-pill text-bg-warning text-dark mb-2 modal-pieza-badge-tienda" style="white-space:normal;">
                                <i class="bi bi-shop" aria-hidden="true"></i>
                                <span class="modal-pieza-badge-tienda-texto"></span>
                            </span>

                            <ul class="list-unstyled small modal-pieza-ficha mt-2">
                                <li><strong>Metal:</strong> <span class="modal-pieza-metal"></span></li>
                                <li><strong>Categoría:</strong> <span class="modal-pieza-categoria"></span></li>
                                <li class="modal-pieza-peso-wrap"><strong>Peso:</strong> <span class="modal-pieza-peso"></span> g</li>
                                <li class="modal-pieza-alto-wrap d-none"><strong>Alto:</strong> <span class="modal-pieza-alto"></span></li>
                                <li class="modal-pieza-ancho-wrap d-none"><strong>Ancho:</strong> <span class="modal-pieza-ancho"></span></li>
                                <li><strong>Disponibilidad:</strong> <span class="modal-pieza-stock"></span></li>
                            </ul>

                            <div class="modal-pieza-variantes-wrap d-none mb-3">
                                <div class="modal-pieza-variantes-ejes"></div>
                                <div class="modal-pieza-variante-color-wrap d-none mb-2">
                                    <label class="form-label fw-semibold mb-2 modal-pieza-variante-color-label">Selecciona color:</label>
                                    <div class="modal-pieza-variante-color-opciones d-flex flex-wrap gap-2" role="radiogroup" aria-label="Colores disponibles"></div>
                                </div>
                                <div class="modal-pieza-variante-talla-wrap d-none mb-2">
                                    <label class="form-label fw-semibold mb-2 modal-pieza-variante-talla-label">Selecciona talla:</label>
                                    <div class="modal-pieza-variante-talla-opciones d-flex flex-wrap gap-2" role="radiogroup" aria-label="Tallas disponibles"></div>
                                </div>
                                <div class="modal-pieza-variante-simple-wrap d-none">
                                    <label class="form-label fw-semibold mb-2 modal-pieza-variante-label">Selecciona opción:</label>
                                    <div class="modal-pieza-variante-opciones d-flex flex-wrap gap-2" role="radiogroup" aria-label="Variantes disponibles"></div>
                                </div>
                                <p class="small text-muted modal-pieza-variante-hint mb-0 mt-2"></p>
                                <p class="small fw-semibold text-success modal-pieza-variante-seleccion mb-0 mt-2 d-none"></p>
                            </div>

                            <div class="alert alert-warning small modal-pieza-leyenda-entrega mb-3" role="note">
                                <strong>Entrega exclusivamente en tienda.</strong>
                                Al confirmar tu pago, la pieza queda apartada en la sucursal
                                <strong class="modal-pieza-leyenda-tienda"></strong>
                                y la podrás recoger con identificación oficial y tu número de orden.
                                No realizamos envíos a domicilio.
                            </div>

                            <button type="button" class="btn btn-dark btn-lg w-100 modal-pieza-btn-agregar" disabled>
                                <i class="bi bi-cart-plus" aria-hidden="true"></i>
                                Agregar al carrito
                            </button>
                            <div class="modal-pieza-feedback small mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
