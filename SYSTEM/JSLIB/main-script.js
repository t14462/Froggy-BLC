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



function DlIncrement(id) {
    const el = document.getElementById(id);

    if(!el) {
        return;
    }

    const count = parseInt(el.textContent, 10);

    if(Number.isNaN(count)) {
        el.textContent = "1";
    } else {
        el.textContent = String(count + 1);
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
    document.querySelectorAll('img:not(header img):not(body>aside img):not(footer img)').forEach(img => {
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








    var links = document.querySelectorAll('#obyava a[href], article a[href], #comm-section a[href]');
    var currentHost = location.hostname.replace(/^www\./i, '').toLowerCase();

    links.forEach(function (link) {
        var href = link.getAttribute('href');

        if (!href) {
            return;
        }

        href = href.trim();

        // Пропускаем якоря и специальные схемы
        if (
            href.charAt(0) === '#' ||
            /^(mailto|tel|javascript|data):/i.test(href)
        ) {
            return;
        }

        var url;

        try {
            // Автоматически превращает относительные ссылки в абсолютные
            url = new URL(href, location.href);
        } catch (e) {
            return;
        }

        // Пропускаем не-web ссылки
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return;
        }

        var linkHost = url.hostname.replace(/^www\./i, '').toLowerCase();

        // Пропускаем текущий сайт:
        // /page
        // ?Article/1
        // https://твой-сайт/...
        // https://www.твой-сайт/...
        if (linkHost === currentHost) {
            return;
        }

        // Внешняя ссылка
        link.setAttribute('target', '_blank');

        // Безопасность для target="_blank"
        var rel = link.getAttribute('rel') || '';
        var relParts = rel.toLowerCase().split(/\s+/);

        if (relParts.indexOf('noopener') === -1) {
            rel += ' noopener';
        }

        if (relParts.indexOf('noreferrer') === -1) {
            rel += ' noreferrer';
        }

        link.setAttribute('rel', rel.trim());
    });






});





//////////////////////////////////////////////////































/*
 * Проверка имени пользователя.
 *
 * Разрешаем любые нормальные печатные символы, включая пробелы.
 * Запрещаем:
 *   - пустое имя;
 *   - управляющие символы;
 *   - переводы строк.
 *
 * Дубликаты имён дополнительно проверяются evaluateUserList().
 */
function validateUsername(input) {

    input.value = input.value.trim();

    const value = input.value;
    const length = Array.from(value).length;

    let valid = true;

    if(length < 3 || length > 25) {
        valid = false;
    }

    /*
    if(value === '') {
        valid = false;
    }
    */

    if(/[\x00-\x1F\x7F\\]/.test(value)) {
        valid = false;
    }

    input.classList.toggle('invalidField', !valid);

    evaluateUserList();

    return valid;
}


/*
 * Проверка SHA-512 hash.
 *
 * 128 шестнадцатеричных символов.
 */
function validateUserHash(input) {

    const value = input.value.trim();

    const valid = /^[0-9a-f]{128}$/i.test(value);

    input.classList.toggle('invalidField', !valid);

    evaluateUserList();

    return valid;
}


/*
 * Аналог PHP:
 *
 * htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
 */
function htmlspecialchars(value) {

    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/*
 * Проверка всего списка пользователей и генерация результата.
 */
function evaluateUserList() {

    const forms = Array.from(document.querySelectorAll('.user-form'));
    const output = document.getElementById('generatedUserArrayOutput');

    const usernames = new Map();

    /*
     * Сначала проверяем каждое поле отдельно.
     */
    for(const form of forms) {

        const nameInput = form.querySelector('.user-name');
        const hashInput = form.querySelector('.user-hash');
        const privilegesSelect = form.querySelector('.user-privileges');

        const name = nameInput.value.trim();
        const nameLength = Array.from(name).length;
        const hash = hashInput.value.trim();
        const privileges = privilegesSelect.value;

        const nameValid =
            // name.trim() !== '' &&
            nameLength >= 3 &&
            nameLength <= 25 &&
            !/[\x00-\x1F\x7F\\]/.test(name);

        const hashValid =
            /^[0-9a-f]{128}$/i.test(hash);

        const privilegesValid =
            /^[0-4]$/.test(privileges);

        nameInput.classList.toggle('invalidField', !nameValid);
        hashInput.classList.toggle('invalidField', !hashValid);
        privilegesSelect.classList.toggle('invalidField', !privilegesValid);

        /*
         * Собираем имена, чтобы потом найти дубликаты.
         */
        if(nameValid) {

            if(!usernames.has(name)) {
                usernames.set(name, []);
            }

            usernames.get(name).push(nameInput);
        }
    }


    /*
     * Дублирующиеся имена пользователей недопустимы.
     * Подсвечиваем ВСЕ экземпляры дубликата.
     */
    for(const inputs of usernames.values()) {

        if(inputs.length > 1) {

            for(const input of inputs) {
                input.classList.add('invalidField');
            }
        }
    }


    /*
     * Генерируем PHP-массив.
     *
     * Строка генерируется только для полностью корректной записи.
     */
    const lines = [];

    for(const form of forms) {

        const nameInput = form.querySelector('.user-name');
        const hashInput = form.querySelector('.user-hash');
        const privilegesSelect = form.querySelector('.user-privileges');

        /*
         * Если хотя бы одно поле ошибочно —
         * эту запись в результат не включаем.
         */
        if(
            nameInput.classList.contains('invalidField') ||
            hashInput.classList.contains('invalidField') ||
            privilegesSelect.classList.contains('invalidField')
        ) {
            continue;
        }

        const name = htmlspecialchars(nameInput.value.trim());
        const privileges = privilegesSelect.value;
        const hash = hashInput.value.trim();

        lines.push(
            `$cred['${name}'] = "${privileges}<!!!>${hash}";`
        );
    }


    output.value = lines.join('\n');
}


/*
 * Удаление конкретной формы пользователя.
 */
function removeThisUserForm(button) {

    const confirmed = confirm('Вы действительно хотите удалить пользователя?');

    if(!confirmed) {
        return;
    }

    const form = button.closest('.user-form');

    if(form) {
        form.remove();
    }

    evaluateUserList();
}


/*
 * Добавление нового пользователя.
 *
 * В текущей HTML-разметке кнопки добавления пока нет,
 * но функция уже готова.
 *
 * Например:
 *
 * <button onclick="addUserForm()">Добавить пользователя</button>
 */
function addUserForm() {

    const marker = document.getElementById('insertUserFormBefore');

    const form = document.createElement('div');
    form.className = 'user-form';

    form.innerHTML = `
<input type="text" class="user-name" value="" onchange="validateUsername(this)" />

<select class="user-privileges" onchange="evaluateUserList()">
<option selected="selected">0</option>
<option>1</option>
<option>2</option>
<option>3</option>
<option>4</option>
</select>

<input type="text" class="user-hash" value="" onchange="validateUserHash(this)" />

<button type="button" onclick="removeThisUserForm(this)">🗑️</button>
`;

    marker.parentNode.insertBefore(form, marker);

    /*
     * Новые пустые поля сразу показываем как ошибочные.
     */
    evaluateUserList();

    form.querySelector('.user-name').focus();
}


/*
 * Инициализация после построения DOM.
 *
 * Дополнительно включаем живое обновление во время набора,
 * а не только после onchange.
 */
document.addEventListener('DOMContentLoaded', function() {

    document.addEventListener('input', function(event) {

        if(
            event.target.matches('.user-name') ||
            event.target.matches('.user-hash')
        ) {
            evaluateUserList();
        }
    });

    document.querySelector('.user-form')?.remove();

    evaluateUserList();
});
