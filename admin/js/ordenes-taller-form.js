(function () {
    var origenSelect = document.getElementById('origen');
    var bloqueInventario = document.getElementById('bloque-inventario');
    var bloqueClientePieza = document.getElementById('bloque-cliente-pieza');
    var piezaDescripcion = document.getElementById('pieza_descripcion');
    var codigoInput = document.getElementById('codigo_busqueda');
    var idStockInput = document.getElementById('id_pieza_stock_FK');
    var stockResumen = document.getElementById('stock-resumen');
    var btnBuscar = document.getElementById('btn-buscar-stock');
    var anticipoMonto = document.getElementById('anticipo_monto');
    var formaAnticipo = document.getElementById('id_forma_pago_anticipo');

    if (!origenSelect) {
        return;
    }

    function toggleOrigen() {
        var esInventario = origenSelect.value === 'inventario';
        if (bloqueInventario) {
            bloqueInventario.style.display = esInventario ? '' : 'none';
        }
        if (bloqueClientePieza) {
            bloqueClientePieza.style.display = esInventario ? 'none' : '';
        }
        if (piezaDescripcion) {
            piezaDescripcion.readOnly = esInventario;
            piezaDescripcion.required = !esInventario;
            if (esInventario) {
                piezaDescripcion.removeAttribute('required');
            }
        }
        if (!esInventario && idStockInput) {
            idStockInput.value = '';
            if (stockResumen) {
                stockResumen.innerHTML = '';
            }
        }
    }

    function buscarStock() {
        var codigo = codigoInput ? codigoInput.value.trim() : '';
        if (!codigo) {
            alert('Capture o escanee un codigo.');
            return;
        }

        fetch('api/ordenes_taller.php?accion=buscar_stock&codigo=' + encodeURIComponent(codigo), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success || !data.stock) {
                    alert(data.error || 'No se encontro la pieza.');
                    return;
                }
                var s = data.stock;
                if (idStockInput) {
                    idStockInput.value = s.id_pieza_stock;
                }
                if (piezaDescripcion) {
                    piezaDescripcion.value = s.desc_pieza || '';
                }
                if (stockResumen) {
                    stockResumen.innerHTML = '<i class="bi bi-gem"></i> ' +
                        (s.desc_pieza || '') + ' — ' +
                        (s.codigo_auxiliar || s.codigo_barras || '') +
                        ' (' + (s.estado || '') + ')';
                }
            })
            .catch(function () {
                alert('Error al buscar la pieza.');
            });
    }

    origenSelect.addEventListener('change', toggleOrigen);
    toggleOrigen();

    if (btnBuscar) {
        btnBuscar.addEventListener('click', buscarStock);
    }
    if (codigoInput) {
        codigoInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarStock();
            }
        });
    }

    if (anticipoMonto && formaAnticipo) {
        anticipoMonto.addEventListener('input', function () {
            var monto = parseFloat(anticipoMonto.value || '0');
            formaAnticipo.required = monto > 0;
        });
    }
})();
