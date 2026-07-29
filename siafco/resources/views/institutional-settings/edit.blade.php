<x-layouts.app title="Configuracion institucional">
    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
        <form method="post" enctype="multipart/form-data" action="{{ route('institutional-settings.update') }}" class="section-card grid gap-4 md:grid-cols-2">
            @csrf
            @method('put')
            <div class="md:col-span-2">
                <label class="form-label">Nombre institucion</label>
                <input class="form-input" name="institution_name" value="{{ old('institution_name', $setting->institution_name) }}" data-uppercase required>
            </div>
            <div>
                <label class="form-label">Color principal</label>
                <input class="form-input h-12" type="color" name="primary_color" value="{{ old('primary_color', $setting->primary_color) }}" required>
            </div>
            <div>
                <label class="form-label">Color secundario</label>
                <input class="form-input h-12" type="color" name="secondary_color" value="{{ old('secondary_color', $setting->secondary_color) }}" required>
            </div>
            <div>
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" name="email" value="{{ old('email', $setting->email) }}">
            </div>
            <div>
                <label class="form-label">Telefono</label>
                <input class="form-input" name="phone" value="{{ old('phone', $setting->phone) }}">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Direccion</label>
                <input class="form-input" name="address" value="{{ old('address', $setting->address) }}" data-uppercase>
            </div>
            <div>
                <label class="form-label">Logo</label>
                <input class="form-input" type="file" name="logo" accept="image/*">
            </div>
            <section class="rounded border border-[#d4af37]/40 bg-[#fff8df] p-4 md:col-span-2">
                <p class="text-xs font-black uppercase text-[#b8942f]">QR institucional de pago</p>
                <p class="mt-2 text-sm text-slate-700">Administrado desde Afiliación &gt; QR y pago institucional.</p>
                <a class="btn-secondary mt-3" href="{{ route('institutional-qr.show') }}">GESTIONAR QR DE PAGO</a>
            </section>

            <section class="grid gap-4 border-t border-slate-200 pt-6 md:col-span-2 md:grid-cols-2" data-login-appearance-editor>
                <div class="md:col-span-2">
                    <p class="text-xs font-black uppercase tracking-wide text-[#b8942f]">Apariencia del inicio de sesión</p>
                    <h2 class="mt-1 text-xl font-black text-[#0b1f3a]">Identidad visual del acceso</h2>
                    <p class="mt-1 text-sm text-slate-600">Fondo recomendado: 1600 × 900 px o superior. JPG, PNG o WEBP, máximo 5 MB.</p>
                </div>

                <div>
                    <label class="form-label" for="login_background">Imagen de fondo del login</label>
                    <input id="login_background" class="form-input" type="file" name="login_background" accept="image/jpeg,image/png,image/webp" data-login-image-input="background">
                    <input type="hidden" name="remove_login_background" value="0" data-login-remove-input="background">
                    <button class="btn-secondary mt-2" type="button" data-login-remove="background">Eliminar imagen</button>
                </div>

                <div>
                    <label class="form-label" for="login_logo">Logotipo del login</label>
                    <input id="login_logo" class="form-input" type="file" name="login_logo" accept="image/jpeg,image/png,image/webp" data-login-image-input="logo">
                    <input type="hidden" name="remove_login_logo" value="0" data-login-remove-input="logo">
                    <button class="btn-secondary mt-2" type="button" data-login-remove="logo">Eliminar imagen</button>
                </div>

                <div>
                    <label class="form-label" for="login_title">Título principal</label>
                    <input id="login_title" class="form-input" name="login_title" value="{{ old('login_title', $setting->login_title ?: 'SISTEMA DE AFILIACIÓN') }}" maxlength="120" data-login-copy="title" required>
                </div>

                <div>
                    <label class="form-label" for="login_institution_name">Nombre de la institución</label>
                    <input id="login_institution_name" class="form-input" name="login_institution_name" value="{{ old('login_institution_name', $setting->login_institution_name ?: 'COOPERATIVA TIERRA BENDITA') }}" maxlength="180" data-login-copy="institution" required>
                </div>

                <div class="md:col-span-2">
                    <label class="form-label" for="login_affiliate_message">Mensaje para el afiliado</label>
                    <textarea id="login_affiliate_message" class="form-input" name="login_affiliate_message" rows="4" maxlength="800" data-login-copy="message">{{ old('login_affiliate_message', $setting->login_affiliate_message ?: $setting->loginAppearance()['affiliate_message']) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <label class="form-label" for="login_overlay_opacity">Intensidad de la capa oscura</label>
                        <output class="text-sm font-black text-[#0b1f3a]" data-login-opacity-output>{{ old('login_overlay_opacity', $setting->login_overlay_opacity ?: 65) }}%</output>
                    </div>
                    <input id="login_overlay_opacity" class="w-full accent-[#d4af37]" type="range" name="login_overlay_opacity" min="20" max="90" value="{{ old('login_overlay_opacity', $setting->login_overlay_opacity ?: 65) }}" data-login-opacity required>
                </div>
            </section>

            <div class="md:col-span-2">
                <button class="btn-primary">Guardar configuracion</button>
            </div>
        </form>

        <aside class="section-card">
            <h2 class="text-xl font-black text-[#0b1f3a]">Vista actual</h2>
            <div class="mt-4 grid place-items-center rounded border border-slate-200 bg-slate-50 p-5">
                @if($setting->logoUrl())
                    <img class="h-28 w-28 object-contain" src="{{ $setting->logoUrl() }}" alt="Logo">
                @else
                    <div class="grid h-28 w-28 place-items-center rounded bg-[#0b1f3a] font-black text-[#d4af37]">SIAFCO</div>
                @endif
            </div>
            <dl class="mt-5 grid gap-3 text-sm">
                <div><dt class="font-black text-slate-500">Institucion</dt><dd>{{ $setting->institution_name }}</dd></div>
                <div><dt class="font-black text-slate-500">Correo</dt><dd>{{ $setting->email ?: 'Sin dato' }}</dd></div>
                <div><dt class="font-black text-slate-500">Telefono</dt><dd>{{ $setting->phone ?: 'Sin dato' }}</dd></div>
            </dl>

            <section class="mt-6 border-t border-slate-200 pt-5">
                <p class="text-xs font-black uppercase text-[#b8942f]">Exportación de credenciales</p>
                <dl class="mt-3 grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-black text-slate-600">PDF</dt>
                        <dd class="font-bold {{ $exportCapabilities->canExportPdf() ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $exportCapabilities->canExportPdf() ? 'Disponible' : 'No disponible' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-black text-slate-600">PNG</dt>
                        <dd class="font-bold {{ $exportCapabilities->canExportPng() ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $exportCapabilities->canExportPng() ? 'Disponible' : 'No disponible' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="font-black text-slate-600">Impresión</dt>
                        <dd class="font-bold {{ $exportCapabilities->canPrintCredential() ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $exportCapabilities->canPrintCredential() ? 'Disponible' : 'No disponible' }}
                        </dd>
                    </div>
                    <div><dt class="font-black text-slate-500">Motor PDF</dt><dd>{{ $exportCapabilities->pdfEngine() }}</dd></div>
                    <div><dt class="font-black text-slate-500">Motor PNG</dt><dd>{{ $exportCapabilities->pngEngine() }}</dd></div>
                    <div><dt class="font-black text-slate-500">Motor impresión</dt><dd>Navegador</dd></div>
                </dl>
                @if($exportCapabilities->pngUnavailableReason())
                    <p class="mt-3 text-xs leading-5 text-slate-600">{{ $exportCapabilities->pngUnavailableReason() }}</p>
                @endif
            </section>
            @php($loginAppearance = $setting->loginAppearance())
            <div
                class="relative mt-6 aspect-video overflow-hidden rounded border border-slate-200 bg-[#0b1f3a] bg-cover bg-center"
                style="background-image: url('{{ $loginAppearance['background_url'] }}')"
                data-login-preview
            >
                <div class="absolute inset-0 bg-[#041226]" style="opacity: {{ $loginAppearance['overlay_opacity'] }}" data-login-preview-overlay></div>
                <div class="absolute inset-0 flex items-center gap-3 p-4 text-white">
                    <div class="grid h-14 w-14 flex-none place-items-center overflow-hidden rounded-full border border-[#d4af37]/70">
                        <img class="h-full w-full object-contain {{ $loginAppearance['logo_url'] ? '' : 'hidden' }}" src="{{ $loginAppearance['logo_url'] ?: '' }}" alt="" data-login-preview-logo>
                        <span class="font-black text-[#d4af37] {{ $loginAppearance['logo_url'] ? 'hidden' : '' }}" data-login-preview-logo-fallback>S</span>
                    </div>
                    <div class="min-w-0">
                        <strong class="block truncate text-sm" data-login-preview-title>{{ $loginAppearance['title'] }}</strong>
                        <span class="block truncate text-xs font-bold text-[#d4af37]" data-login-preview-institution>{{ $loginAppearance['institution_name'] }}</span>
                        <p class="mt-1 line-clamp-2 text-[9px] text-white/80" data-login-preview-message>{{ $loginAppearance['affiliate_message'] }}</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.app>
