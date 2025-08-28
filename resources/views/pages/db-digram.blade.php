<x-filament-panels::page>
<x-filament::button size="sm" onclick="getCopyContent()">
    Update Digram
</x-filament::button>


<erd-editor id="erdEditor" automatic-layout system-dark-mode enable-theme-builder></erd-editor>
<script>
    const checkModuleSupport = () => 'supports' in HTMLScriptElement
        ? HTMLScriptElement.supports('module')
        : 'noModule' in document.createElement('script');

    const createScript = (src) => {
        return checkModuleSupport()
            ? import(src)
            : new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.onload = () => resolve();
                script.onerror = () => reject();
                script.src = src;
                document.body.appendChild(script);
            });
    };

    const loader = (src, fallback) => {
        return createScript(src).catch(() => createScript(fallback));
    };

    const sql = atob('{{ base64_encode(File::get($path)) }}');
    const editor = document.querySelector('erd-editor');

    if (checkModuleSupport()) {
        const scripts = [[
            '{{ asset('vendor/laravel-erd/erd-editor.esm.js') }}',
            'https://cdn.jsdelivr.net/npm/@dineug/erd-editor/+esm',
        ], [
            '{{ asset('vendor/laravel-erd/erd-editor-shiki-worker.esm.js') }}',
            'https://cdn.jsdelivr.net/npm/@dineug/erd-editor-shiki-worker/+esm',
        ]].map(([src, fallback]) => loader(src, fallback));

        Promise.all(scripts)
            .then(([{ setGetShikiServiceCallback }, { getShikiService }]) => setGetShikiServiceCallback(getShikiService))
            .then(() => editor.setSchemaSQL(sql));
    } else {
        const scripts = [[
            '{{ asset('vendor/laravel-erd/vuerd.min.js') }}',
            'https://cdn.jsdelivr.net/npm/vuerd/dist/vuerd.min.js',
        ]].map(([src, fallback]) => loader(src, fallback));

        Promise.all(scripts).then(() => editor.loadSQLDDL(sql));
    }
</script>

<script>

  function getCopyContent() {
            const editor = document.querySelector('erd-editor');
            const sqlContent = editor.getSchemaSQL();
            @this.call('saveSql', sqlContent);
            // Optional: Store in localStorage
            // localStorage.setItem('sqlContent', sqlContent);
        }

    
</script>

</x-filament-panels::page>
