(function () {
    "use strict";

    var ANCHO = 400;     
    var MAX_URL = 250;    
    var MAX_LADO = 800;   

    function pedir(url, buscador) {
        return buscador(url).then(function (r) {
            if (!r.ok) { throw new Error("HTTP " + r.status); }
            return r.json();
        }).then(function (datos) {
            var paginas = (datos.query && datos.query.pages) ? Object.keys(datos.query.pages).map(function (k) { return datos.query.pages[k]; }) : [];
            return paginas.sort(function (a, b) { return (a.index || 0) - (b.index || 0); });
        });
    }

    function consultarWikipedia(idioma, texto, buscador) {
        var params = new URLSearchParams({
            action: "query", format: "json", origin: "*",
            generator: "search", gsrnamespace: "0", gsrsearch: texto, gsrlimit: "10",
            prop: "pageimages", piprop: "thumbnail", pithumbsize: String(ANCHO), pilicense: "any"
        });
        return pedir("https://" + idioma + ".wikipedia.org/w/api.php?" + params.toString(), buscador);
    }

    function consultarCommons(texto, buscador) {
        var params = new URLSearchParams({
            action: "query", format: "json", origin: "*",
            generator: "search", gsrnamespace: "6", gsrsearch: texto, gsrlimit: "30",
            prop: "imageinfo", iiprop: "url|mime|size", iiurlwidth: String(ANCHO)
        });
        return pedir("https://commons.wikimedia.org/w/api.php?" + params.toString(), buscador);
    }

    function deWikipedia(pagina, incluirJpg) {
        var mini = pagina.thumbnail;
        var url = mini && mini.source;
        if (!url || url.length > MAX_URL) { return null; }
        var esPng = /\.png$/i.test(url);             
        var esJpg = /\.jpe?g$/i.test(url);
        if (!(esPng || (incluirJpg && esJpg))) { return null; }
        return { titulo: pagina.title || "", url: url, fuente: "Wikipedia" };
    }

    function deCommons(pagina, incluirJpg) {
        var info = pagina.imageinfo && pagina.imageinfo[0];
        if (!info) { return null; }
        var esPng = info.mime === "image/png";
        var esSvg = info.mime === "image/svg+xml";
        var esJpg = info.mime === "image/jpeg";
        if (!(esPng || esSvg || (incluirJpg && esJpg))) { return null; }
        var url = info.thumburl || info.url;
        if (!url || url.length > MAX_URL) { return null; }
        if (esSvg && !/\.png$/i.test(url)) { return null; }   
        return {
            titulo: String(pagina.title || "").replace(/^(File|Archivo):/i, "").replace(/\.[a-z0-9]+$/i, ""),
            url: url,
            fuente: "Commons"
        };
    }

    function buscarImagenes(texto, opciones, buscador) {
        opciones = opciones || {};
        buscador = buscador || window.fetch.bind(window);
        var base = String(texto || "").trim();
        if (!base) { return Promise.resolve([]); }

        var incluirJpg = !!opciones.incluirJpg;
        var extendida = base + (opciones.sufijo || "");

        var consultas = [
            consultarWikipedia("es", base, buscador),
            consultarWikipedia("en", base, buscador)
        ].concat(incluirJpg
            ? [consultarCommons(extendida + " filetype:bitmap", buscador), consultarCommons(extendida + " filetype:drawing", buscador)]
            : [consultarCommons(extendida + " filemime:png", buscador), consultarCommons(extendida + " filetype:drawing", buscador)]);

        return Promise.allSettled(consultas).then(function (estados) {
            if (!estados.some(function (e) { return e.status === "fulfilled"; })) {
                throw new Error("sin conexión");
            }
            function valor(i) { return estados[i].status === "fulfilled" ? estados[i].value : []; }

            var es = valor(0).map(function (p) { return deWikipedia(p, incluirJpg); }).filter(Boolean);
            var en = valor(1).map(function (p) { return deWikipedia(p, incluirJpg); }).filter(Boolean);
            var commons = valor(2).concat(valor(3)).map(function (p) { return deCommons(p, incluirJpg); }).filter(Boolean);

            // Se intercalan los resultados en español e inglés para que las dos
            // Wikipedias tengan la misma oportunidad de aparecer arriba, y los
            // de Commons se agregan después.
            var ordenados = [];
            for (var i = 0; i < Math.max(es.length, en.length); i++) {
                if (es[i]) { ordenados.push(es[i]); }
                if (en[i]) { ordenados.push(en[i]); }
            }
            ordenados = ordenados.concat(commons);

            var vistas = {};
            return ordenados.filter(function (r) {
                if (vistas[r.url]) { return false; }
                vistas[r.url] = true;
                return true;
            }).slice(0, 40);
        });
    }

    function aPng(blob) {
        return new Promise(function (resolver, rechazar) {
            var direccion = URL.createObjectURL(blob);
            var img = new Image();
            img.onload = function () {
                var escala = Math.min(1, MAX_LADO / Math.max(img.naturalWidth, img.naturalHeight));
                var lienzo = document.createElement("canvas");
                lienzo.width = Math.max(1, Math.round(img.naturalWidth * escala));
                lienzo.height = Math.max(1, Math.round(img.naturalHeight * escala));
                lienzo.getContext("2d").drawImage(img, 0, 0, lienzo.width, lienzo.height);
                lienzo.toBlob(function (png) {
                    URL.revokeObjectURL(direccion);
                    if (png) { resolver(png); } else { rechazar(new Error("no se pudo convertir")); }
                }, "image/png");
            };
            img.onerror = function () { URL.revokeObjectURL(direccion); rechazar(new Error("imagen no válida")); };
            img.src = direccion;
        });
    }

    function pareceDireccion(texto) {
        return /^https?:\/\/\S+$/i.test(String(texto || "").trim());
    }

    function el(etiqueta, atributos, texto) {
        var e = document.createElement(etiqueta);
        Object.keys(atributos || {}).forEach(function (k) { e.setAttribute(k, atributos[k]); });
        if (texto !== undefined) { e.textContent = texto; }
        return e;
    }

    var ventana = null;
    var actual = null;    
    var elegida = null;     
    var turno = 0;           

    function crearVentana() {
        var fondo = el("div", { "class": "il-fondo" });
        fondo.hidden = true;

        var caja = el("div", { "class": "il-ventana", role: "dialog", "aria-modal": "true", "aria-labelledby": "il-titulo" });

        var cab = el("div", { "class": "il-cabecera" });
        cab.appendChild(el("h3", { id: "il-titulo" }, "Imágenes en línea"));
        var cerrar = el("button", { type: "button", "class": "il-cerrar", "aria-label": "Cerrar" }, "×");
        cab.appendChild(cerrar);

        var form = el("form", { "class": "il-busqueda" });
        var campo = el("input", { type: "search", placeholder: "Nombre del equipo o jugador...", "aria-label": "Texto a buscar" });
        form.appendChild(campo);
        form.appendChild(el("button", { type: "submit", "class": "il-buscar" }, "Buscar"));

        var opcion = el("label", { "class": "il-opcion" });
        var jpg = el("input", { type: "checkbox" });
        opcion.appendChild(jpg);
        opcion.appendChild(document.createTextNode(" Incluir también fotos JPG (si no, solo PNG)"));

        var estado = el("p", { "class": "il-estado", role: "status" });
        var resultados = el("div", { "class": "il-resultados" });
        var google = el("div", { "class": "il-google" });
        google.appendChild(el("p", { "class": "il-google-titulo" }, "¿No aparece la imagen exacta? Búscala en Google:"));
        var botonesGoogle = el("div", { "class": "il-google-botones" });
        var abrirGoogle = el("button", { type: "button", "class": "il-abrir-google" }, "Abrir Google Imágenes ↗");
        var pegar = el("button", { type: "button", "class": "il-pegar" }, "📋 Pegar imagen copiada");
        botonesGoogle.appendChild(abrirGoogle);
        botonesGoogle.appendChild(pegar);
        google.appendChild(botonesGoogle);
        google.appendChild(el("p", { "class": "il-ayuda-google" },
            "En Google: clic derecho sobre la imagen → «Copiar imagen». Vuelve aquí y pulsa «Pegar imagen copiada» (o Ctrl + V)."));
        var avisoPegar = el("p", { "class": "il-aviso-pegar", role: "status" });
        google.appendChild(avisoPegar);

        var pie = el("div", { "class": "il-pie" });
        var manual = el("details", { "class": "il-manual" });
        manual.appendChild(el("summary", {}, "O pega la dirección de una imagen"));
        var filaManual = el("div", { "class": "il-fila-manual" });
        var urlManual = el("input", { type: "url", placeholder: "https://...", "aria-label": "Dirección de la imagen" });
        var usarManual = el("button", { type: "button", "class": "il-usar-url" }, "Usar");
        filaManual.appendChild(urlManual);
        filaManual.appendChild(usarManual);
        manual.appendChild(filaManual);
        var avisoManual = el("p", { "class": "il-aviso-manual" });
        manual.appendChild(avisoManual);

        var acciones = el("div", { "class": "il-acciones" });
        acciones.appendChild(el("span", { "class": "il-fuente" }, "Fuentes: Wikipedia y Wikimedia Commons"));
        var cancelar = el("button", { type: "button", "class": "il-cancelar" }, "Cancelar");
        var insertar = el("button", { type: "button", "class": "il-insertar" }, "Insertar");
        insertar.disabled = true;
        acciones.appendChild(cancelar);
        acciones.appendChild(insertar);

        pie.appendChild(manual);
        pie.appendChild(acciones);

        caja.appendChild(cab);
        caja.appendChild(form);
        caja.appendChild(opcion);
        caja.appendChild(estado);
        caja.appendChild(resultados);
        caja.appendChild(google);
        caja.appendChild(pie);
        fondo.appendChild(caja);
        document.body.appendChild(fondo);

        function cerrarVentana() {
            fondo.hidden = true;
            document.body.classList.remove("il-abierto");
            turno++;
            if (actual && actual.boton) { actual.boton.focus(); }
        }

        function elegir(url, miniatura) {
            elegida = url;
            insertar.disabled = !url;
            Array.prototype.forEach.call(resultados.children, function (h) {
                h.classList.toggle("il-elegida", h === miniatura);
            });
        }

        function insertarElegida() {
            if (elegida && actual) { actual.poner(elegida); }
            cerrarVentana();
        }

        function mostrar(lista) {
            resultados.innerHTML = "";
            elegir(null, null);
            lista.forEach(function (r) {
                var b = el("button", { type: "button", "class": "il-resultado", title: r.titulo + " (" + r.fuente + ")" });
                var marco = el("span", { "class": "il-miniatura" });
                marco.appendChild(el("img", { src: r.url, alt: r.titulo, loading: "lazy" }));
                b.appendChild(marco);
                b.appendChild(el("span", { "class": "il-leyenda" }, r.titulo));
                b.addEventListener("click", function () { elegir(r.url, b); });
                b.addEventListener("dblclick", function () { elegir(r.url, b); insertarElegida(); });
                resultados.appendChild(b);
            });
        }

        function ejecutarBusqueda() {
            var texto = campo.value.trim();
            if (!texto) { estado.textContent = "Escribe qué imagen quieres buscar."; return; }
            var mio = ++turno;
            estado.textContent = "Buscando...";
            resultados.innerHTML = "";
            elegir(null, null);
            buscarImagenes(texto, { incluirJpg: jpg.checked, sufijo: actual ? actual.sufijo : "" }).then(function (lista) {
                if (mio !== turno) { return; }
                mostrar(lista);
                estado.textContent = lista.length
                    ? lista.length + " imágenes. Elige una y pulsa Insertar (o haz doble clic)."
                    : "No encontré imágenes para «" + texto + "». Prueba con el nombre completo u oficial, en inglés, o usa Google más abajo.";
            }).catch(function () {
                if (mio !== turno) { return; }
                estado.textContent = "No se pudo conectar. Revisa tu conexión a internet e inténtalo de nuevo.";
            });
        }

        function recibirImagen(blob) {
            avisoPegar.textContent = "Procesando la imagen...";
            avisoPegar.className = "il-aviso-pegar";
            aPng(blob).then(function (png) {
                return actual.ponerArchivo(png);
            }).then(function () {
                cerrarVentana();
            }).catch(function (e) {
                avisoPegar.className = "il-aviso-pegar il-error";
                avisoPegar.textContent = e && e.message === "sin-archivo"
                    ? "Tu navegador no permite agregarla automáticamente. Guarda la imagen en tu equipo y usa «Subir imagen .png»."
                    : "No se pudo usar esa imagen. Prueba con otra o guárdala y usa «Subir imagen .png».";
            });
        }

        function recibirTexto(texto) {
            var t = String(texto || "").trim();
            if (!pareceDireccion(t)) { return false; }
            if (t.length > MAX_URL) {
                avisoPegar.className = "il-aviso-pegar il-error";
                avisoPegar.textContent = "La dirección es demasiado larga (máximo " + MAX_URL + " caracteres). Copia la imagen en lugar de su dirección.";
                return true;
            }
            elegida = t;
            insertarElegida();
            return true;
        }

        document.addEventListener("paste", function (e) {
            if (fondo.hidden || !e.clipboardData) { return; }
            var archivos = e.clipboardData.files || [];
            for (var i = 0; i < archivos.length; i++) {
                if (/^image\//.test(archivos[i].type)) { e.preventDefault(); recibirImagen(archivos[i]); return; }
            }
            var objetivo = e.target && e.target.tagName;
            if (objetivo !== "INPUT" && objetivo !== "TEXTAREA") {
                if (recibirTexto(e.clipboardData.getData("text"))) { e.preventDefault(); }
            }
        });

        pegar.addEventListener("click", function () {
            avisoPegar.className = "il-aviso-pegar";
            if (!navigator.clipboard || !navigator.clipboard.read) {
                avisoPegar.textContent = "Tu navegador no permite leer el portapapeles desde aquí. Presiona Ctrl + V con esta ventana abierta.";
                return;
            }
            navigator.clipboard.read().then(function (elementos) {
                for (var i = 0; i < elementos.length; i++) {
                    var tipo = elementos[i].types.filter(function (t) { return /^image\//.test(t); })[0];
                    if (tipo) { return elementos[i].getType(tipo).then(recibirImagen); }
                }
                return navigator.clipboard.readText().then(function (texto) {
                    if (!recibirTexto(texto)) {
                        avisoPegar.className = "il-aviso-pegar il-error";
                        avisoPegar.textContent = "No hay ninguna imagen copiada. En Google haz clic derecho sobre la imagen y elige «Copiar imagen».";
                    }
                });
            }).catch(function () {
                avisoPegar.className = "il-aviso-pegar il-error";
                avisoPegar.textContent = "No se pudo leer el portapapeles. Presiona Ctrl + V con esta ventana abierta.";
            });
        });

        abrirGoogle.addEventListener("click", function () {
            var texto = campo.value.trim() + (actual ? actual.sufijo : "");
            window.open("https://www.google.com/search?tbm=isch&hl=es&q=" + encodeURIComponent(texto.trim()), "_blank", "noopener");
        });

        form.addEventListener("submit", function (e) { e.preventDefault(); ejecutarBusqueda(); });
        jpg.addEventListener("change", function () { if (campo.value.trim()) { ejecutarBusqueda(); } });
        insertar.addEventListener("click", insertarElegida);
        cancelar.addEventListener("click", cerrarVentana);
        cerrar.addEventListener("click", cerrarVentana);
        fondo.addEventListener("mousedown", function (e) { if (e.target === fondo) { cerrarVentana(); } });
        document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !fondo.hidden) { cerrarVentana(); } });

        usarManual.addEventListener("click", function () {
            var u = urlManual.value.trim();
            if (!pareceDireccion(u)) { avisoManual.textContent = "Escribe una dirección que empiece con http:// o https://"; return; }
            if (u.length > MAX_URL) { avisoManual.textContent = "La dirección es demasiado larga (máximo " + MAX_URL + " caracteres)."; return; }
            avisoManual.textContent = "";
            elegida = u;
            insertarElegida();
        });

        return {
            abrir: function (bloque, textoInicial) {
                actual = bloque;
                campo.value = textoInicial || "";
                jpg.checked = !!bloque.incluirJpg;
                urlManual.value = "";
                avisoManual.textContent = "";
                avisoPegar.textContent = "";
                manual.open = false;
                resultados.innerHTML = "";
                estado.textContent = "";
                elegir(null, null);
                fondo.hidden = false;
                document.body.classList.add("il-abierto");
                campo.focus();
                if (campo.value.trim()) { ejecutarBusqueda(); }
            }
        };
    }

    function iniciarBloque(bloque) {
        var nombreCampo = bloque.getAttribute("data-campo");
        var campoOculto = document.getElementById(nombreCampo);
        if (!campoOculto) { return; }

        var raiz = nombreCampo.replace(/_url$/, "");
        var sufijo = bloque.getAttribute("data-sufijo") || "";

        var vista = el("div", { "class": "il-vista" });
        var imagen = el("img", { alt: "Imagen elegida" });
        var texto = el("span", { "class": "il-vista-texto" }, "Imagen elegida");
        var quitar = el("button", { type: "button", "class": "il-quitar" }, "Quitar");
        vista.appendChild(imagen);
        vista.appendChild(texto);
        vista.appendChild(quitar);

        var boton = el("button", { type: "button", "class": "btn-buscar-imagen" });

        function refrescar() {
            var url = campoOculto.value.trim();
            vista.hidden = !url;
            if (url) { imagen.src = url; }
            boton.textContent = url ? "🔍 Cambiar imagen" : "🔍 Buscar imágenes en línea";
        }

        function poner(url) {
            campoOculto.value = url;
            refrescar();
        }

        function ponerArchivo(png) {
            var entrada = document.querySelector('input[name="' + raiz + '_archivo"]');
            var opcion = document.querySelector('input[name="tipo_' + raiz + '"][value="archivo"]');
            if (!entrada || !opcion || typeof DataTransfer === "undefined" || typeof File === "undefined") {
                return Promise.reject(new Error("sin-archivo"));
            }
            try {
                var archivo = new File([png], raiz + "_pegada.png", { type: "image/png" });
                var contenedor = new DataTransfer();
                contenedor.items.add(archivo);
                entrada.files = contenedor.files;
            } catch (e) {
                return Promise.reject(new Error("sin-archivo"));
            }

            campoOculto.value = "";
            refrescar();
            opcion.checked = true;
            opcion.dispatchEvent(new Event("change", { bubbles: true }));  

            var anterior = entrada.parentNode.querySelector(".il-vista-archivo");
            if (anterior) { anterior.remove(); }
            var previa = el("div", { "class": "il-vista il-vista-archivo" });
            var direccion = URL.createObjectURL(png);
            previa.appendChild(el("img", { src: direccion, alt: "Imagen pegada" }));
            previa.appendChild(el("span", { "class": "il-vista-texto" }, "Imagen pegada de Google: se guardará como PNG al enviar el formulario"));
            entrada.parentNode.appendChild(previa);
            entrada.addEventListener("change", function () { previa.remove(); }, { once: true });
            return Promise.resolve();
        }

        boton.addEventListener("click", function () {
            if (!ventana) { ventana = crearVentana(); }
            var origen = bloque.getAttribute("data-consulta");
            var campoTexto = origen ? document.querySelector(origen) : null;
            ventana.abrir({
                boton: boton,
                poner: poner,
                ponerArchivo: ponerArchivo,
                sufijo: sufijo,
                incluirJpg: bloque.getAttribute("data-incluir-jpg") === "1"
            }, campoTexto ? campoTexto.value.trim() : "");
        });
        quitar.addEventListener("click", function () { poner(""); });

        bloque.appendChild(vista);
        bloque.appendChild(boton);
        refrescar();
    }

    function iniciar() {
        Array.prototype.forEach.call(document.querySelectorAll("[data-imagen-linea]"), iniciarBloque);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", iniciar);
    } else {
        iniciar();
    }

    window.ImagenesEnLinea = { buscar: buscarImagenes };
})();