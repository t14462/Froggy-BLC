function ready(fn) {

    if(document.readyState !== 'loading') {

        fn();

    } else {

        document.addEventListener('DOMContentLoaded', fn);
    }
}

/*
 * ready(function () {
 * // do something here...
 * });
 */



function countChars(obj) {
    document.getElementById("symcount").innerText = (obj.getAttribute("maxlength") - obj.value.length) + ' Осталось.';
}


//Refresh Captcha
function refreshCaptcha() {
    var img = document.images['captcha_image'];
    img.src = img.src.substring(
        0,img.src.lastIndexOf("?")
        )+"?time="+Date.now();
}


async function copyToClipboard(textToCopy) {
    // Navigator clipboard api needs a secure context (https)
    if(navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(textToCopy);
    } else {
        // Use the 'out of viewport hidden text area' trick
        const textArea = document.createElement("textarea");
        textArea.value = textToCopy;

        // Move textarea out of the viewport so it's not visible
        textArea.style.position = "absolute";
        textArea.style.left = "-999999px";

        document.body.prepend(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
        } catch (error) {
            console.error(error);
        } finally {
            textArea.remove();
        }
    }
}









ready(function () {

    
    var s44noscripts = document.querySelectorAll(".not-js");

    if(s44noscripts.length > 0) {
        for (var i = 0; i < s44noscripts.length; i++) {
            s44noscripts[i].style.display = "none";
        }
    }



    
    var s44links = document.querySelectorAll("a.active");
    for (var i = 0; i < s44links.length; i++) {
        s44links[i].addEventListener("click", function (e) {
            e.preventDefault();
        });

        s44links[i].style.pointerEvents = "none";
        s44links[i].setAttribute("aria-disabled", "true");
        s44links[i].setAttribute("tabindex", "-1");
    }





    // Создаем lightbox overlay и элементы
    const lightboxOverlay = document.createElement('div');
    lightboxOverlay.className = 'lightbox-overlay';
    const lightboxImage = document.createElement('img');
    lightboxImage.className = 'lightbox-image';
    const lightboxClose = document.createElement('span');
    lightboxClose.className = 'lightbox-close';
    lightboxClose.textContent = '×';

    // Добавляем элементы в overlay
    lightboxOverlay.appendChild(lightboxImage);
    lightboxOverlay.appendChild(lightboxClose);
    document.body.appendChild(lightboxOverlay);

    // Открываем изображение в lightbox при клике на него
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('click', function () {
            lightboxImage.src = this.src;
            lightboxOverlay.style.display = 'flex';
        });
    });

    // Закрываем lightbox при клике на overlay или кнопку закрытия
    lightboxOverlay.addEventListener('click', function (e) {
        if(e.target === lightboxOverlay || e.target === lightboxClose) {
            lightboxOverlay.style.display = 'none';
        }
    });


    //////////////////////////


    const headers = document.querySelectorAll("article h2, article h3, article h4, article h5, article h6");
    const counters = [0,0,0,0,0]; // для h2..h6

    headers.forEach(h => {
        const level = parseInt(h.tagName[1]); // "H3" -> 3
        const index = level - 2; // h2=0, h3=1 и т.д.

        counters[index]++;

        // сбрасываем более глубокие уровни
        for (let i = index + 1; i < counters.length; i++) {
            counters[i] = 0;
        }

        // собираем номер начиная с h2
        const numbering = counters
            .slice(0, index + 1)
            .filter(n => n > 0)
            .join(".");

        h.insertAdjacentHTML("beforeend", " <span class='hnum'>" + numbering + "</span>");

        //h.textContent = "<span class='hnum'>" + numbering + "</span> " + h.textContent;
    });







    (() => {
        const MIN_CHARS = 2;
        const MAX_RESULTS = 50;

        const scope   = document.querySelector('#sitemenu');
        const input   = document.getElementById('menuSearch');
        const results = document.getElementById('menuSearchResults');
        if (!scope || !input || !results) return;

        // Индекс: [normText, rawText, href]
        const entries = [];
        scope.querySelectorAll('a[itemprop="name"][href]').forEach(a => {
            const raw = (a.textContent || '').trim();
            if (!raw) return;
            const hrefAttr = a.getAttribute('href') || '';
            if (!hrefAttr || hrefAttr.startsWith('#')) return; // отсечём якоря
            const href = a.href; // абсолютный URL для перехода
            entries.push([ norm(raw), raw, href ]);
        });

        const onInput = debounce(runSearch, 90);
        input.addEventListener('input', onInput);

        let lastQ = '';
        function runSearch() {
            const q = norm(input.value);
            if (q === lastQ) return;
            lastQ = q;

            if (q.length < MIN_CHARS) {
                results.textContent = '';
                return;
            }

            const tokens = q.split(' ').filter(Boolean);
            if (!tokens.length) {
                results.textContent = '';
                return;
            }

            // Фильтрация: AND по всем словам
            const out = [];
            outer: for (let i = 0; i < entries.length; i++) {
                const e = entries[i][0]; // normText
                for (let t = 0; t < tokens.length; t++) {
                    if (e.indexOf(tokens[t]) === -1) continue outer;
                }
                out.push(entries[i]);
                if (out.length >= MAX_RESULTS) break;
            }

            // Подсветка: компилируем регексы для всех токенов
            const regs = tokens.map(yoInsensitiveRegex);
            render(out, regs);
        }

        function render(items, regs) {
            results.textContent = '';
            if (!items.length) return;

            const frag = document.createDocumentFragment();
            for (let i = 0; i < items.length; i++) {
                const [, raw, href] = items[i];
                const li  = document.createElement('li');
                const a   = document.createElement('a');
                a.href = href;
                a.innerHTML = highlightWithRegexes(raw, regs); // подсветка в тексте
                li.appendChild(a);
                frag.appendChild(li);
            }
            results.appendChild(frag);
        }

        // ── Подсветка и утилиты ──────────────────────────────────────────────
        function highlightWithRegexes(raw, regs){
            if (!regs.length) return esc(raw);
            let ranges = [];
            for (const re of regs){
                re.lastIndex = 0;
                let m;
                while ((m = re.exec(raw)) !== null){
                    ranges.push([m.index, m.index + m[0].length]);
                    if (m.index === re.lastIndex) re.lastIndex++; // предохранитель на пустые матчи
                }
            }
            if (!ranges.length) return esc(raw);
            ranges.sort((a,b)=> a[0]-b[0] || a[1]-b[1]);

            const merged = [];
            let [s,e] = ranges[0];
            for (let i=1;i<ranges.length;i++){
                const [cs,ce] = ranges[i];
                if (cs <= e) e = Math.max(e, ce);
                else { merged.push([s,e]); [s,e] = [cs,ce]; }
            }
            merged.push([s,e]);

            let out = '', cur = 0;
            for (const [ms, me] of merged) {
                if (cur < ms) out += esc(raw.slice(cur, ms));
                out += '<mark>' + esc(raw.slice(ms, me)) + '</mark>';
                cur = me;
            }
            if (cur < raw.length) out += esc(raw.slice(cur));
            return out;
        }

        // Регекс, нечувствительный к кириллическим е/ё
        function yoInsensitiveRegex(token){
            const safe = String(token).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pat  = safe.replace(/[её]/giu, '[её]');
            return new RegExp(pat, 'giu');
        }

        function esc(s){
            return String(s).replace(/[&<>"']/g, m =>
                m==='&'?'&amp;': m==='<'?'&lt;': m==='>'?'&gt;': m === '"' ? '&quot;' : '&#39;'
            );
        }

        // Нормализация: нижний регистр, NFKC, ё→е, схлоп пробелов
        function norm(s){
            return (s || '')
                .toLowerCase()
                .normalize('NFKC')
                .replace(/ё/g, 'е')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function debounce(fn, ms){
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), ms); };
        }
    })();






});





//////////////////////////////////////////////////







