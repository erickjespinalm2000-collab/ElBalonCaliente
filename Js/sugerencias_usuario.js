(function () {
    "use strict";

    var URL_DISPONIBLE = "../Php/usuario_disponible.php";
    var MIN = 4;           
    var MAX = 20;          
    var MOSTRAR = 5;       
    var PARTICULAS = ["de", "del", "la", "las", "los", "y", "da", "di"];

    function limpiar(texto) {
        return String(texto || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]/g, "");
    }

    function palabras(texto, quitarParticulas) {
        var todas = String(texto || "").trim().split(/\s+/).map(limpiar).filter(Boolean);
        if (!quitarParticulas) { return todas; }
        var utiles = todas.filter(function (p) { return PARTICULAS.indexOf(p) === -1; });
        return utiles.length ? utiles : todas;
    }

    function inicial(p) { return p ? p.charAt(0) : ""; }

    function unicos(lista) {
        var vistos = {};
        return lista.filter(function (x) {
            if (!x || x.length < MIN || x.length > MAX || vistos[x]) { return false; }
            vistos[x] = true;
            return true;
        });
    }

    function generar(nombre, apellido) {
        var ns = palabras(nombre, false);
        var as = palabras(apellido, true);
        if (!ns.length || !as.length) { return { base: [], numerados: [] }; }

        var n1 = ns[0], n2 = ns[1] || "";
        var a1 = as[0], a2 = as[1] || "";

        var base = unicos([
            n1 + a1,                                   
            inicial(n1) + a1,                          
            n2 ? inicial(n1) + inicial(n2) + a1 : "",  
            n1 + "_" + a1,                            
            n1 + "." + a1,                             
            a1 + n1,                                   
            a2 ? n1 + a2 : "",                        
            a2 ? inicial(n1) + a1 + inicial(a2) : "", 
            n2 && a2 ? inicial(n1) + inicial(n2) + a1 + inicial(a2) : "", 
            a1 + "_" + n1,                         
            n1 + inicial(a1),                          
            a1 + inicial(n1),                          
            a2 ? n1 + inicial(a1) + inicial(a2) : ""   
        ]);

        var suma = n1.length + a1.length;
        var numeros = [];
        [suma, 7, 10, 21, 23, 99].forEach(function (n) { if (numeros.indexOf(n) === -1) { numeros.push(n); } });

        var numerados = [];
        base.slice(0, 4).forEach(function (b) {
            numeros.forEach(function (n) { numerados.push(b + n); });
        });
        numerados = unicos(numerados);

        return { base: base, numerados: numerados };
    }

    function elegir(base, numerados, ocupados) {
        var tomados = {};
        (ocupados || []).forEach(function (u) { tomados[String(u).toLowerCase()] = true; });
        function libre(u) { return !tomados[u]; }

        var libresBase = base.filter(libre);
        var resultado = libresBase.slice(0, MOSTRAR);

        if (libresBase.length < 3) {
            numerados.filter(libre).forEach(function (u) {
                if (resultado.length < MOSTRAR && resultado.indexOf(u) === -1) { resultado.push(u); }
            });
        }
        return resultado;
    }

    function consultarOcupados(candidatos, buscador) {
        buscador = buscador || window.fetch.bind(window);
        var url = URL_DISPONIBLE + "?u=" + encodeURIComponent(candidatos.join(","));
        return buscador(url, { cache: "no-store" }).then(function (r) {
            if (!r.ok) { throw new Error("HTTP " + r.status); }
            return r.json();
        }).then(function (datos) {
            if (!datos || !Array.isArray(datos.ocupados)) { throw new Error("respuesta inválida"); }
            return datos.ocupados;
        });
    }

    function iniciar() {
        var nombre = document.getElementById("nombre");
        var apellido = document.getElementById("apellido");
        var usuario = document.getElementById("usuario");
        var caja = document.getElementById("sugerencias");
        var lista = document.getElementById("sugerencias-lista");
        var estado = document.getElementById("estado-usuario");
        if (!nombre || !apellido || !usuario || !caja || !lista || !estado) { return; }

        var escritoAMano = usuario.value.trim() !== "";
        var turnoSugerir = 0, turnoEstado = 0, temporizadorSugerir = null, temporizadorEstado = null;

        function mensaje(texto, clase) {
            estado.textContent = texto;
            estado.className = "estado-usuario" + (clase ? " " + clase : "");
        }

        function pintar(sugerencias) {
            lista.innerHTML = "";
            sugerencias.forEach(function (s) {
                var b = document.createElement("button");
                b.type = "button";
                b.className = "chip-sugerencia";
                b.textContent = s;
                b.addEventListener("click", function () {
                    usuario.value = s;
                    escritoAMano = true;
                    comprobarEscrito();
                    usuario.focus();
                });
                lista.appendChild(b);
            });
            caja.hidden = sugerencias.length === 0;
        }

        function comprobarEscrito() {
            var valor = usuario.value.trim();
            var mio = ++turnoEstado;
            if (!valor) { mensaje("", ""); return; }
            if (!/^[A-Za-z0-9_.]+$/.test(valor)) {
                mensaje("Usa solo letras, números, punto y guion bajo (sin espacios ni tildes).", "no");
                return;
            }
            mensaje("Comprobando...", "");
            consultarOcupados([valor]).then(function (ocupados) {
                if (mio !== turnoEstado) { return; }
                var tomado = ocupados.some(function (u) { return u.toLowerCase() === valor.toLowerCase(); });
                mensaje(tomado ? "Ese usuario ya está en uso." : "Usuario disponible ✓", tomado ? "no" : "ok");
            }).catch(function () {
                if (mio !== turnoEstado) { return; }
                mensaje("", "");
            });
        }

        function sugerir() {
            var mio = ++turnoSugerir;
            var combos = generar(nombre.value, apellido.value);
            if (!combos.base.length) {
                pintar([]);
                usuario.placeholder = "nombre y apellido";
                return;
            }

            function aplicar(elegidas, verificadas) {
                if (mio !== turnoSugerir) { return; }
                pintar(elegidas);
                if (elegidas.length) { usuario.placeholder = elegidas[0]; }
                if (!escritoAMano && elegidas.length) {
                    usuario.value = elegidas[0];
                    mensaje(verificadas ? "Usuario disponible ✓" : "", verificadas ? "ok" : "");
                }
            }

            consultarOcupados(combos.base.concat(combos.numerados)).then(function (ocupados) {
                aplicar(elegir(combos.base, combos.numerados, ocupados), true);
            }).catch(function () {
                aplicar(combos.base.slice(0, MOSTRAR), false);
            });
        }

        function programar() {
            clearTimeout(temporizadorSugerir);
            temporizadorSugerir = setTimeout(sugerir, 350);
        }

        nombre.addEventListener("input", programar);
        apellido.addEventListener("input", programar);
        usuario.addEventListener("input", function () {
            escritoAMano = usuario.value.trim() !== "";
            clearTimeout(temporizadorEstado);
            temporizadorEstado = setTimeout(comprobarEscrito, 350);
        });

        if (nombre.value.trim() && apellido.value.trim()) { sugerir(); }
        if (usuario.value.trim()) { comprobarEscrito(); }
    }

    if (typeof document !== "undefined") {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", iniciar);
        } else {
            iniciar();
        }
    }

    if (typeof window !== "undefined") {
        window.SugerenciasUsuario = { generar: generar, elegir: elegir, consultarOcupados: consultarOcupados };
    }
})();