<div class="kc-page" wire:loading.class="kc-page--busy" wire:target="nextPage,previousPage,save,confirmDelete">
    <div class="kc-page__header">
        <div>
            <h2 class="kc-page__title">Kelola Mesin</h2>
            <p class="kc-page__subtitle">Machinery Group beserta Machinery di dalamnya, termasuk data Asuransi dan Pajak/Pembelian</p>
        </div>

        <div class="kc-page__actions">
            <button type="button" wire:click="openCreateGroupForm" class="kc-button kc-button--ghost" data-testid="add-group">
                + Tambah Grup
            </button>
            <button type="button" wire:click="openCreateForm" class="kc-button kc-button--primary" data-testid="add-machinery">
                + Tambah Mesin
            </button>
        </div>
    </div>

    {{--
        Alih mode + pencarian + filter dalam satu x-filter.bar. Mode Grup
        (bawaan) hierarkis; mode Rata mengembalikan daftar rata seluruh
        Machinery seperti layar lama — tanpa mode itu, membandingkan mesin
        lintas grup hanya mungkin lewat pencarian. Filter ketiga mengikuti
        mode: Station di mode Grup, Machinery Group di mode Rata; yang
        dihitung "aktif" hanya filter yang tampil di mode itu.
    --}}
    <x-filter.bar label="Filter mesin" testid="view-toolbar" :total="$meta['total']" :noun="$viewMode === 'grup' ? 'grup' : 'mesin'"
                  :active="$this->activeFilterCount($viewMode === 'grup' ? ['filterMachineryGroupId'] : ['filterStationId'])" reset="resetFilters">
        <x-filter.field label="Tampilan" size="auto">
            <div class="fb-segment" role="group" aria-label="Mode tampilan">
                <button
                    type="button"
                    wire:click="setViewMode('grup')"
                    @class(['fb-segment__btn', 'fb-segment__btn--active' => $viewMode === 'grup'])
                    data-testid="mode-grup"
                    @if ($viewMode === 'grup') aria-current="true" @endif
                ><x-filter.icon name="layers" />Grup</button>
                <button
                    type="button"
                    wire:click="setViewMode('rata')"
                    @class(['fb-segment__btn', 'fb-segment__btn--active' => $viewMode === 'rata'])
                    data-testid="mode-rata"
                    @if ($viewMode === 'rata') aria-current="true" @endif
                ><x-filter.icon name="list" />Rata</button>
            </div>
        </x-filter.field>

        <x-filter.field label="Cari" for="search" icon="search" size="grow">
            <x-filter.search
                id="search"
                model="search"
                :value="$search"
                placeholder="Kode atau nama grup / mesin"
                data-testid="search-input"
            />
        </x-filter.field>

        @if ($viewMode === 'rata')
            <x-filter.field label="Machinery Group" for="filterMachineryGroupId" icon="layers" size="lg">
                <x-searchable-select
                    id="filterMachineryGroupId"
                    wire:model.live="filterMachineryGroupId"
                    :options="collect($machineryGroupOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['label']])->all()"
                    placeholder="Semua Machinery Group"
                    class="fb-control"
                />
            </x-filter.field>
        @else
            <x-filter.field label="Station" for="filterStationId" icon="station" size="lg">
                <x-searchable-select
                    id="filterStationId"
                    wire:model.live="filterStationId"
                    :options="collect($stationOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['label']])->all()"
                    placeholder="Semua Station"
                    class="fb-control"
                />
            </x-filter.field>
        @endif

        @if ($viewMode === 'grup' && $ungroupedCount > 0)
            <x-slot:note>
                <span data-testid="ungrouped-count">{{ $ungroupedCount }} mesin tanpa grup</span> — tampil di wadah "Tanpa grup" di bawah daftar.
            </x-slot:note>
        @endif
    </x-filter.bar>

    @if ($successMessage)
        <div class="kc-alert kc-alert--success" role="status" data-testid="success-message">
            {{ $successMessage }}
        </div>
    @endif

    @if ($deleteErrorMessage)
        <div class="kc-alert" role="alert">
            {{ $deleteErrorMessage }}
        </div>
    @endif

    @if ($deleteGroupErrorMessage)
        <div class="kc-alert" role="alert" data-testid="delete-group-error">
            {{ $deleteGroupErrorMessage }}
        </div>
    @endif


    @if ($viewMode === 'grup')
        {{--
            Mode Grup. Paginasi dihitung PER GRUP: mesin yang tampil karena
            grupnya dibuka datang dari permintaan lain (listMachinery), jadi
            tidak pernah ikut menambah baris halaman. Dengan 223 grup
            berisi rata-rata 3 mesin, menghitung baris akan membuat satu
            halaman melar jadi ~80 baris.
        --}}
        <div class="kc-table-wrap">
            <table class="kc-table" data-testid="group-table">
                <thead class="kc-table__head">
                    <tr>
                        <th class="kc-table__toggle-head"><span class="kc-sr-only">Buka</span></th>
                        <th>Kode Grup</th>
                        <th>Business Unit</th>
                        <th>Station</th>
                        <th>Production Line</th>
                        <th>Jumlah Mesin</th>
                        <th class="kc-table__actions-head">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groupRows as $group)
                        @php $isOpen = in_array($group['id'], $expandedGroupIds, true); @endphp
                        <tr class="kc-table__row kc-table__row--group" wire:key="group-{{ $group['id'] }}" data-testid="group-row">
                            <td>
                                <button
                                    type="button"
                                    wire:click="toggleGroup('{{ $group['id'] }}')"
                                    class="kc-button kc-button--ghost kc-button--sm"
                                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                    data-testid="toggle-group"
                                {{-- Karakter UTF-8 langsung, BUKAN HTML entity: `{{ }}` meng-escape
                                     keluarannya, jadi '&#9662;' akan tampil sebagai teks mentah
                                     "&#9662;" alih-alih ▾. Memakai {!! !!} juga bisa, tetapi
                                     karakter langsung tidak menambah permukaan unescaped sama
                                     sekali. --}}
                                >{{ $isOpen ? '▾' : '▸' }}</button>
                            </td>
                            <td>{{ $group['group_code'] }}</td>
                            <td>{{ $group['business_unit_name'] ?? '-' }}</td>
                            <td>{{ $group['station_name'] ?? '-' }}</td>
                            <td>{{ $group['production_line_name'] ?? '-' }}</td>
                            <td>{{ $group['machinery_count'] }}</td>
                            <td class="kc-table__actions">
                                @if ($confirmingDeleteGroupId === $group['id'])
                                    <span class="kc-confirm">
                                        <span class="kc-confirm__label">Yakin hapus grup?</span>
                                        <button type="button" wire:click="confirmDeleteGroup" class="kc-button kc-button--danger kc-button--sm">
                                            Ya, Hapus
                                        </button>
                                        <button type="button" wire:click="cancelDeleteGroup" class="kc-button kc-button--ghost kc-button--sm">
                                            Batal
                                        </button>
                                    </span>
                                @else
                                    <button type="button" wire:click="openCreateForm('{{ $group['id'] }}')" class="kc-button kc-button--ghost kc-button--sm" data-testid="add-machinery-in-group">
                                        + Mesin
                                    </button>
                                    <button type="button" wire:click="openEditGroupForm('{{ $group['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="askDeleteGroup('{{ $group['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                        Hapus
                                    </button>
                                @endif
                            </td>
                        </tr>

                        @if ($isOpen)
                            {{-- Kolom grup yang jarang dibaca pindah ke sini, bukan dihapus. --}}
                            <tr class="kc-table__row kc-table__row--static kc-table__row--panel" wire:key="group-panel-{{ $group['id'] }}">
                                <td colspan="7">
                                    <dl class="kc-detail-panel" data-testid="group-panel">
                                        <div><dt>Deskripsi</dt><dd>{{ $group['description'] ?? '-' }}</dd></div>
                                        <div><dt>Unit</dt><dd>{{ $group['unit'] ?? '-' }}</dd></div>
                                        <div><dt>Workshop Factor</dt><dd>{{ $group['workshop_factor'] ?? '-' }}</dd></div>
                                        <div><dt>Cost per Equipment</dt><dd>{{ $group['cost_per_equipment'] ?? '-' }}</dd></div>
                                        <div><dt>Dibuat Pada</dt><dd>{{ $group['created_at'] ? \Illuminate\Support\Carbon::parse($group['created_at'])->format('d/m/Y H:i') : '-' }}</dd></div>
                                    </dl>
                                </td>
                            </tr>

                            @forelse ($machineryByGroup[$group['id']] ?? [] as $machinery)
                                <tr class="kc-table__row kc-table__row--child" wire:key="child-{{ $machinery['id'] }}" data-testid="machinery-row">
                                    <td></td>
                                    <td>{{ $machinery['equipment_code'] }}</td>
                                    <td colspan="3">{{ $machinery['name'] }}</td>
                                    <td>{{ $machinery['equipment_type'] ?? '-' }} &middot; {{ $machinery['brand'] ?? '-' }}</td>
                                    <td class="kc-table__actions">
                                        @if ($confirmingDeleteId === $machinery['id'])
                                            <span class="kc-confirm">
                                                <span class="kc-confirm__label">Yakin hapus?</span>
                                                <button type="button" wire:click="confirmDelete" class="kc-button kc-button--danger kc-button--sm">Ya, Hapus</button>
                                                <button type="button" wire:click="cancelDelete" class="kc-button kc-button--ghost kc-button--sm">Batal</button>
                                            </span>
                                        @else
                                            <button type="button" wire:click="openEditForm('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">Edit</button>
                                            <button type="button" wire:click="askDelete('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">Hapus</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="kc-table__row kc-table__row--static kc-table__row--panel" wire:key="child-empty-{{ $group['id'] }}">
                                    <td colspan="7">
                                        <p class="kc-empty__subtitle kc-empty__subtitle--nested" data-testid="group-empty">Grup ini belum berisi mesin.</p>
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    @empty
                        <tr class="kc-table__row kc-table__row--static">
                            <td colspan="7">
                                <div class="kc-empty">
                                    <div class="kc-empty__illustration" aria-hidden="true">&#9881;&#65039;</div>
                                    <p class="kc-empty__title">
                                        {{ $search !== '' ? 'Tidak ada yang cocok dengan pencarian' : 'Belum ada Machinery Group' }}
                                    </p>
                                    <p class="kc-empty__subtitle">
                                        {{ $search !== '' ? 'Coba kata kunci lain.' : 'Klik "Tambah Grup" untuk menambahkan data baru.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                    {{--
                        Mesin tanpa grup. machinery_group_id nullable, jadi tanpa
                        wadah ini baris seperti itu lenyap dari layar tanpa pesan.
                        Hanya tampil bila memang ada isinya.
                    --}}
                    @if ($ungroupedCount > 0)
                        <tr class="kc-table__row kc-table__row--group" wire:key="ungrouped-header" data-testid="ungrouped-bucket">
                            <td></td>
                            <td colspan="4"><strong>Tanpa grup</strong></td>
                            {{-- Jumlah di kolom "Jumlah Mesin", bukan di kolom Aksi. --}}
                            <td>{{ $ungroupedCount }}</td>
                            <td></td>
                        </tr>
                        @foreach ($ungroupedRows as $machinery)
                            <tr class="kc-table__row kc-table__row--child" wire:key="ungrouped-{{ $machinery['id'] }}" data-testid="machinery-row">
                                <td></td>
                                <td>{{ $machinery['equipment_code'] }}</td>
                                <td colspan="3">{{ $machinery['name'] }}</td>
                                <td>{{ $machinery['equipment_type'] ?? '-' }} &middot; {{ $machinery['brand'] ?? '-' }}</td>
                                <td class="kc-table__actions">
                                    <button type="button" wire:click="openEditForm('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">Edit</button>
                                    <button type="button" wire:click="askDelete('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @else
        <div class="kc-table-wrap">
            <table class="kc-table">
                <thead class="kc-table__head">
                    <tr>
                        <th>Kode Equipment</th>
                        <th>Nama</th>
                        <th>Machinery Group</th>
                        <th>Tipe</th>
                        <th>Merk</th>
                        <th>Dibuat Pada</th>
                        <th class="kc-table__actions-head">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machineryRows as $machinery)
                        <tr class="kc-table__row" wire:key="machinery-{{ $machinery['id'] }}">
                            <td>{{ $machinery['equipment_code'] }}</td>
                            <td>{{ $machinery['name'] }}</td>
                            <td>{{ $machinery['machinery_group_code'] ?? '-' }}</td>
                            <td>{{ $machinery['equipment_type'] ?? '-' }}</td>
                            <td>{{ $machinery['brand'] ?? '-' }}</td>
                            <td>{{ $machinery['created_at'] ? \Illuminate\Support\Carbon::parse($machinery['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                            <td class="kc-table__actions">
                                @if ($confirmingDeleteId === $machinery['id'])
                                    <span class="kc-confirm">
                                        <span class="kc-confirm__label">Yakin hapus?</span>
                                        <button type="button" wire:click="confirmDelete" class="kc-button kc-button--danger kc-button--sm">
                                            Ya, Hapus
                                        </button>
                                        <button type="button" wire:click="cancelDelete" class="kc-button kc-button--ghost kc-button--sm">
                                            Batal
                                        </button>
                                    </span>
                                @else
                                    <button type="button" wire:click="openEditForm('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="askDelete('{{ $machinery['id'] }}')" class="kc-button kc-button--ghost kc-button--sm kc-button--danger-text">
                                        Hapus
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="kc-table__row kc-table__row--static">
                            <td colspan="7">
                                <div class="kc-empty">
                                    <div class="kc-empty__illustration" aria-hidden="true">&#9881;&#65039;</div>
                                    <p class="kc-empty__title">Belum ada Machinery</p>
                                    <p class="kc-empty__subtitle">Klik "Tambah Machinery" untuk menambahkan data baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($meta['total'] > 0)
        <div class="kc-pagination">
            <span class="kc-pagination__summary">
                Halaman {{ $meta['page'] }} dari {{ $meta['total_pages'] }} ({{ $meta['total'] }} data)
            </span>

            <div class="kc-pagination__controls">
                <button
                    type="button"
                    wire:click="previousPage"
                    class="kc-button kc-button--ghost kc-button--sm"
                    @if ($meta['page'] <= 1) disabled @endif
                >
                    &larr; Sebelumnya
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    class="kc-button kc-button--ghost kc-button--sm"
                    @if ($meta['page'] >= $meta['total_pages']) disabled @endif
                >
                    Berikutnya &rarr;
                </button>
            </div>
        </div>
    @endif

    {{--
        Form Machinery Group. Diserap dari kelola-machinery-group.blade.php.
        Seluruh id diberi awalan `group_` karena `description` dan
        `production_line_display` bentrok dengan form Machinery — hanya satu
        modal terbuka sekaligus sehingga tidak pecah saat jalan, tetapi
        selector browser jadi ambigu tanpa awalan itu.
    --}}
    @if ($showGroupForm)
        <x-modal
            :title="$editingGroupId !== null ? 'Edit Machinery Group' : 'Tambah Machinery Group'"
            wide
            submit="saveGroup"
            backdrop-key="machinery-group-form-backdrop"
        >
            <x-slot:error>
                @if ($groupFormErrorMessage)
                    <div class="kc-alert" role="alert">
                        {{ $groupFormErrorMessage }}
                    </div>
                @endif
            </x-slot:error>

            <div class="kc-form-section">
                <h4 class="kc-form-section__title">Identitas</h4>
                <div class="kc-form-grid">
                    <div class="kc-form-field">
                        <label for="group_station_id" class="kc-form-field__label">
                            Station <span class="kc-form-field__required">*</span>
                        </label>
                        <x-searchable-select
                            id="group_station_id"
                            wire:model.live="station_id"
                            :options="collect($stationOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['label']])->all()"
                            placeholder="-- Pilih Station --"
                            empty-message="Belum ada Station. Buat Station terlebih dahulu."
                            :class="'kc-form-field__input'.($errors->has('station_id') ? ' kc-form-field__input--error' : '')"
                        />
                        @error('station_id')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="group_production_line_display" class="kc-form-field__label">Production Line</label>
                        <p class="kc-form-field__static" id="group_production_line_display">{{ $selectedGroupProductionLineName ?? '-' }}</p>
                        <p class="kc-form-field__hint">Otomatis mengikuti Production Line dari Station yang dipilih.</p>
                    </div>

                    <div class="kc-form-field">
                        <label for="group_code" class="kc-form-field__label">
                            Kode <span class="kc-form-field__required">*</span>
                        </label>
                        <input
                            type="text"
                            id="group_code"
                            wire:model="groupForm.group_code"
                            class="kc-form-field__input @error('groupForm.group_code') kc-form-field__input--error @enderror"
                            autofocus
                        >
                        @error('groupForm.group_code')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="group_unit" class="kc-form-field__label">Unit</label>
                        <input
                            type="text"
                            id="group_unit"
                            wire:model="groupForm.unit"
                            class="kc-form-field__input @error('groupForm.unit') kc-form-field__input--error @enderror"
                        >
                        @error('groupForm.unit')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="group_workshop_factor" class="kc-form-field__label">Workshop Factor</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            id="group_workshop_factor"
                            wire:model="groupForm.workshop_factor"
                            class="kc-form-field__input @error('groupForm.workshop_factor') kc-form-field__input--error @enderror"
                        >
                        @error('groupForm.workshop_factor')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field">
                        <label for="group_cost_per_equipment" class="kc-form-field__label">Cost per Equipment</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            id="group_cost_per_equipment"
                            wire:model="groupForm.cost_per_equipment"
                            class="kc-form-field__input @error('groupForm.cost_per_equipment') kc-form-field__input--error @enderror"
                        >
                        @error('groupForm.cost_per_equipment')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kc-form-field kc-form-field--span2">
                        <label for="group_description" class="kc-form-field__label">Deskripsi</label>
                        <textarea
                            id="group_description"
                            wire:model="groupForm.description"
                            rows="3"
                            class="kc-form-field__input @error('groupForm.description') kc-form-field__input--error @enderror"
                        ></textarea>
                        @error('groupForm.description')
                            <p class="kc-form-field__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <button type="button" wire:click="closeGroupForm" class="kc-button kc-button--ghost">
                    Batal
                </button>
                <button type="submit" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="saveGroup">
                    <span wire:loading.remove wire:target="saveGroup">Simpan</span>
                    <span wire:loading wire:target="saveGroup">Menyimpan&hellip;</span>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    @if ($showForm)
        <x-modal
            :title="$editingId !== null ? 'Edit Machinery' : 'Tambah Machinery'"
            wide
            submit="save"
            backdrop-key="machinery-form-backdrop"
        >
            <x-slot:error>
                @if ($formErrorMessage)
                    <div class="kc-alert" role="alert">
                        {{ $formErrorMessage }}
                    </div>
                @endif
            </x-slot:error>

            {{-- Identity --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Identitas</h4>
                            <div class="kc-form-grid">
                                <div class="kc-form-field">
                                    <label for="machinery_group_id" class="kc-form-field__label">
                                        Machinery Group <span class="kc-form-field__required">*</span>
                                    </label>
                                    <x-searchable-select
                                        id="machinery_group_id"
                                        wire:model.live="machinery_group_id"
                                        :options="collect($machineryGroupOptions)->map(fn ($option) => ['value' => $option['id'], 'label' => $option['label']])->all()"
                                        placeholder="-- Pilih Machinery Group --"
                                        empty-message="Belum ada Machinery Group. Buat Machinery Group terlebih dahulu."
                                        :class="'kc-form-field__input'.($errors->has('machinery_group_id') ? ' kc-form-field__input--error' : '')"
                                    />
                                    @error('machinery_group_id')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="station_display" class="kc-form-field__label">Station</label>
                                    <p class="kc-form-field__static" id="station_display">{{ $selectedStationName ?? '-' }}</p>
                                    <p class="kc-form-field__hint">Otomatis mengikuti Station dari Machinery Group.</p>
                                </div>

                                <div class="kc-form-field">
                                    <label for="production_line_display" class="kc-form-field__label">Production Line</label>
                                    <p class="kc-form-field__static" id="production_line_display">{{ $selectedProductionLineName ?? '-' }}</p>
                                    <p class="kc-form-field__hint">Otomatis mengikuti Production Line dari Machinery Group.</p>
                                </div>

                                <div class="kc-form-field">
                                    <label for="equipment_code" class="kc-form-field__label">
                                        Kode Equipment <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input type="text" id="equipment_code" wire:model="form.equipment_code" class="kc-form-field__input @error('form.equipment_code') kc-form-field__input--error @enderror" autofocus>
                                    @error('form.equipment_code')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="name" class="kc-form-field__label">
                                        Nama <span class="kc-form-field__required">*</span>
                                    </label>
                                    <input type="text" id="name" wire:model="form.name" class="kc-form-field__input @error('form.name') kc-form-field__input--error @enderror">
                                    @error('form.name')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="description" class="kc-form-field__label">Deskripsi</label>
                                    <textarea id="description" wire:model="form.description" rows="2" class="kc-form-field__input @error('form.description') kc-form-field__input--error @enderror"></textarea>
                                    @error('form.description')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field kc-form-field--span2">
                                    <label for="picture" class="kc-form-field__label">Gambar</label>
                                    <input type="file" id="picture" wire:model="picture" class="kc-form-field__input @error('picture') kc-form-field__input--error @enderror">
                                    @error('picture')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror

                                    @if ($picture && ! $errors->has('picture') && in_array(strtolower($picture->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true))
                                        <img src="{{ $picture->temporaryUrl() }}" alt="Preview" class="kc-picture-preview">
                                    @elseif ($existingPictureUrl)
                                        <img src="{{ $existingPictureUrl }}" alt="Gambar tersimpan" class="kc-picture-preview">
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Technical spec --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Spesifikasi Teknis</h4>
                            <div class="kc-form-grid">
                                @foreach ([
                                    'registration_no' => 'No. Registrasi',
                                    'make' => 'Make',
                                    'model' => 'Model',
                                    'equipment_type' => 'Tipe Equipment',
                                    'part_no' => 'No. Part',
                                    'serial_no' => 'No. Serial',
                                    'gearbox' => 'Gearbox',
                                    'motor' => 'Motor',
                                    'mounting' => 'Mounting',
                                    'chain' => 'Chain',
                                    'capacity' => 'Kapasitas',
                                    'brand' => 'Merk',
                                    'fixed_asset' => 'Fixed Asset',
                                    'control_activity' => 'Control Activity',
                                    'owner_ite' => 'Owner ITE',
                                ] as $field => $label)
                                    <div class="kc-form-field">
                                        <label for="{{ $field }}" class="kc-form-field__label">{{ $label }}</label>
                                        <input type="text" id="{{ $field }}" wire:model="form.{{ $field }}" class="kc-form-field__input @error('form.'.$field) kc-form-field__input--error @enderror">
                                        @error('form.'.$field)
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach

                                <div class="kc-form-field">
                                    <label for="rpm" class="kc-form-field__label">RPM</label>
                                    <input type="text" inputmode="decimal" id="rpm" wire:model="form.rpm" class="kc-form-field__input @error('form.rpm') kc-form-field__input--error @enderror">
                                    @error('form.rpm')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="kc-form-field">
                                    <label for="year_made" class="kc-form-field__label">Tahun Pembuatan</label>
                                    <input type="text" inputmode="numeric" id="year_made" wire:model="form.year_made" class="kc-form-field__input @error('form.year_made') kc-form-field__input--error @enderror">
                                    @error('form.year_made')
                                        <p class="kc-form-field__error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Insurance — one row per machinery, plain fields (not a repeatable grid) --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Asuransi</h4>
                            <div class="kc-form-grid">
                                @foreach ([
                                    'ownership' => ['Kepemilikan', 'text'],
                                    'insurance_policy_no' => ['No. Polis', 'text'],
                                    'insurance_company' => ['Perusahaan Asuransi', 'text'],
                                    'insurance_expiry_date' => ['Tgl Kadaluarsa', 'date'],
                                    'premium' => ['Premi', 'decimal'],
                                    'amount_insured' => ['Jml Diasuransikan', 'decimal'],
                                ] as $field => [$label, $type]
                                )
                                    <div class="kc-form-field">
                                        <label for="insurance_{{ $field }}" class="kc-form-field__label">{{ $label }}</label>
                                        <input
                                            type="{{ $type === 'decimal' ? 'text' : $type }}"
                                            @if ($type === 'decimal') inputmode="decimal" @endif
                                            id="insurance_{{ $field }}"
                                            wire:model="insurances.0.{{ $field }}"
                                            class="kc-form-field__input @error('insurances.0.'.$field) kc-form-field__input--error @enderror"
                                        >
                                        @error('insurances.0.'.$field)
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tax / Purchase — one row per machinery, plain fields (not a repeatable grid) --}}
                        <div class="kc-form-section">
                            <h4 class="kc-form-section__title">Pajak &amp; Pembelian</h4>
                            <div class="kc-form-grid">
                                @foreach ([
                                    'purchase_date' => ['Tgl Pembelian', 'date'],
                                    'purchase_cost' => ['Biaya Pembelian', 'decimal'],
                                    'policy_type' => ['Jenis Polis', 'text'],
                                    'contact_name' => ['Nama Kontak', 'text'],
                                    'contact_phone' => ['Telp Kontak', 'text'],
                                    'contact_fax' => ['Fax Kontak', 'text'],
                                    'contact_email' => ['Email Kontak', 'email'],
                                ] as $field => [$label, $type]
                                )
                                    <div class="kc-form-field">
                                        <label for="tax_{{ $field }}" class="kc-form-field__label">{{ $label }}</label>
                                        <input
                                            type="{{ $type === 'decimal' ? 'text' : $type }}"
                                            @if ($type === 'decimal') inputmode="decimal" @endif
                                            id="tax_{{ $field }}"
                                            wire:model="taxPurchases.0.{{ $field }}"
                                            class="kc-form-field__input @error('taxPurchases.0.'.$field) kc-form-field__input--error @enderror"
                                        >
                                        @error('taxPurchases.0.'.$field)
                                            <p class="kc-form-field__error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

            <x-slot:actions>
                <button type="button" wire:click="closeForm" class="kc-button kc-button--ghost">
                    Batal
                </button>
                <button type="submit" class="kc-button kc-button--primary" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan&hellip;</span>
                </button>
            </x-slot:actions>
        </x-modal>
    @endif

    <style>
        /* Design tokens — shared `kc-` classes reused verbatim from
           kelola-machinery-group.blade.php (see that file's own style
           block docblock) plus a small set of NEW classes this screen's
           two repeatable child-row grids need
           (.kc-form-section__header, .kc-grid-empty, .kc-child-grid-wrap,
           .kc-child-grid, .kc-picture-preview) — additive only, nothing
           renamed/removed from the shared set. */
        .kc-page {
            --kc-brand: #249360;
            --kc-brand-hover: #1d7a4e;
            --kc-destructive: #DC2626;
            --kc-destructive-hover: #b91c1c;
            --kc-text: #1f2937;
            --kc-text-muted: #6b7280;
            --kc-border: #d1d5db;
            --kc-radius-input: 6px;
            --kc-radius-button: 8px;
            color: var(--kc-text);
        }

        .kc-page--busy {
            opacity: 0.85;
        }

        .kc-page__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .kc-page__title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }

        .kc-page__subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--kc-text-muted);
        }

        .kc-alert {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: var(--kc-radius-input);
            background: #fef2f2;
            border: 1px solid var(--kc-destructive);
            color: var(--kc-destructive);
            font-size: 14px;
        }

        .kc-alert--success {
            background: #ecfdf5;
            border-color: var(--kc-brand);
            color: var(--kc-brand-hover);
        }

        /* Nilai yang tidak bisa diubah di form ditampilkan sebagai teks
           (konvensi web: tidak ada input disabled/readonly). */
        .kc-form-field__static {
            margin: 0;
            padding: 9px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--kc-text);
        }

        .kc-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--kc-border);
            border-radius: 10px;
            background: #fff;
        }

        .kc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .kc-table__head th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f9fafb;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--kc-text-muted);
            border-bottom: 1px solid var(--kc-border);
            white-space: nowrap;
        }

        .kc-table__actions-head {
            text-align: right;
        }

        /* Lebar kolom toggle disamakan dengan indentasi badan tabel supaya
           judul kolom dan isinya berbaris lurus; tanpa ini lebarnya ditentukan
           browser dan ikut bergeser saat isi kolom lain berubah. */
        .kc-table__toggle-head {
            width: var(--kc-group-indent);
            padding-right: 0;
        }

        /* Teks khusus pembaca layar. TANPA aturan ini kelasnya tidak berarti
           apa-apa dan kata "Buka" tampil sebagai judul kolom di atas tombol
           accordion — terbaca sebagai label nyasar. */
        .kc-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Penghitung "mesin tanpa grup" di toolbar. Kelas ini sudah dipakai di
           markup sejak awal tetapi tidak pernah didefinisikan di halaman ini,
           jadi penghitungnya tampil sebagai teks polos. Bentuknya disamakan
           dengan .kc-badge di kelola-station agar konsisten antarhalaman. */
        .kc-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: var(--kc-text-muted);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .kc-table__row td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--kc-border);
            vertical-align: middle;
        }

        .kc-table__row:last-child td {
            border-bottom: none;
        }

        .kc-table__actions {
            text-align: right;
            white-space: nowrap;
        }

        /* ── Accordion grup ───────────────────────────────────────────────
           Baris grup, panel ringkasnya, dan baris mesin di dalamnya
           sebelumnya TANPA CSS sama sekali: <dl> panel tampil dengan margin
           bawaan browser dan indentasi <dd> 40px, sementara baris mesin tidak
           terbedakan sedikit pun dari baris grup — itu sebabnya terbaca
           berantakan.

           Satu nilai indentasi dipakai bersama lewat custom property supaya
           panel ringkas dan baris mesin SELALU berbaris lurus. Keduanya
           menjorok di bawah kolom teks grup, bukan di bawah tombol buka/tutup
           — kalau nilainya ditulis dua kali, keduanya pasti melenceng cepat
           atau lambat. */
        .kc-table {
            --kc-group-indent: 44px;
            --kc-nest-tint: #fbfcfc;
        }

        .kc-table__row--group > td {
            background: #f9fafb;
            font-weight: 600;
            color: var(--kc-text);
        }

        .kc-table__row--group > td:first-child {
            width: var(--kc-group-indent);
            padding-right: 0;
        }

        .kc-table__row--child > td {
            background: var(--kc-nest-tint);
        }

        /* Sel kosong pertama tiap baris mesin memegang garis panduan tegak
           yang menghubungkan seluruh isi satu grup. Itu yang membuat mata
           tahu di mana sebuah grup berakhir tanpa perlu membaca kodenya. */
        .kc-table__row--child > td:first-child {
            width: var(--kc-group-indent);
            padding-right: 0;
            position: relative;
        }

        .kc-table__row--child > td:first-child::after {
            content: "";
            position: absolute;
            left: 23px;
            top: 0;
            bottom: 0;
            border-left: 2px solid var(--kc-border);
        }

        .kc-table__row--child > td:nth-child(2) {
            color: var(--kc-text-muted);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .kc-table__row--panel > td {
            background: var(--kc-nest-tint);
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .kc-detail-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px 28px;
            margin: 0;
            padding-left: calc(var(--kc-group-indent) - 16px);
        }

        .kc-detail-panel > div {
            min-width: 0;
        }

        .kc-detail-panel dt {
            margin: 0 0 3px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--kc-text-muted);
        }

        /* margin: 0 WAJIB — tanpa ini <dd> membawa indentasi bawaan browser
           sebesar 40px dan seluruh kolom nilai melenceng dari labelnya. */
        .kc-detail-panel dd {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
            color: var(--kc-text);
            overflow-wrap: anywhere;
        }

        .kc-empty__subtitle--nested {
            padding-left: calc(var(--kc-group-indent) - 16px);
            font-style: italic;
        }

        .kc-confirm {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .kc-confirm__label {
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-empty {
            padding: 48px 16px;
            text-align: center;
        }

        .kc-empty__illustration {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .kc-empty__title {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--kc-text);
        }

        .kc-empty__subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-pagination__controls {
            display: flex;
            gap: 8px;
        }

        .kc-button {
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            border-radius: var(--kc-radius-button);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .kc-button--sm {
            padding: 6px 10px;
            font-size: 13px;
        }

        .kc-button--primary {
            background: var(--kc-brand);
            color: #fff;
        }

        .kc-button--primary:hover:not(:disabled) {
            background: var(--kc-brand-hover);
        }

        .kc-button--danger {
            background: var(--kc-destructive);
            color: #fff;
        }

        .kc-button--danger:hover:not(:disabled) {
            background: var(--kc-destructive-hover);
        }

        .kc-button--ghost {
            background: #fff;
            color: var(--kc-text);
            border: 1px solid var(--kc-border);
        }

        .kc-button--ghost:hover:not(:disabled) {
            border-color: var(--kc-brand);
            color: var(--kc-brand);
        }

        .kc-button--danger-text:hover:not(:disabled) {
            border-color: var(--kc-destructive);
            color: var(--kc-destructive);
        }

        .kc-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal shell (backdrop/box/title/scrollable body/actions) now
           lives in the reusable modal Blade component
           (resources/views/components/modal.blade.php, used here as the
           x-modal tag) — it ships its own scoped kcm-modal-* CSS, so the
           old .kc-modal-* rules that used to live here (and had a scroll
           bug: .kc-modal__body's flex:1 had no effect since its parent
           form element wasn't a flex container) have been removed. */

        .kc-form-section {
            margin-bottom: 20px;
        }

        .kc-form-section:last-child {
            margin-bottom: 0;
        }

        .kc-form-section__title {
            margin: 0 0 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--kc-text-muted);
            padding-bottom: 6px;
            border-bottom: 1px solid var(--kc-border);
        }

        .kc-form-section__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--kc-border);
            margin-bottom: 12px;
        }

        .kc-form-section__header .kc-form-section__title {
            margin: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .kc-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 16px;
        }

        .kc-form-field--span2 {
            grid-column: span 2;
        }

        .kc-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .kc-form-field__label {
            font-size: 13px;
            font-weight: 500;
            color: var(--kc-text-muted);
        }

        .kc-form-field__required {
            color: var(--kc-destructive);
        }

        .kc-form-field__input {
            padding: 9px 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--kc-text);
            border: 1px solid var(--kc-border);
            border-radius: var(--kc-radius-input);
            background: #fff;
            width: 100%;
        }

        .kc-form-field__input:disabled {
            background: #f3f4f6;
            color: var(--kc-text-muted);
        }

        .kc-form-field__input:focus {
            outline: none;
            border-color: var(--kc-brand);
            box-shadow: 0 0 0 3px rgba(36, 147, 96, 0.15);
        }

        .kc-form-field__input--error {
            border-color: var(--kc-destructive);
        }

        .kc-form-field__error {
            margin: 0;
            font-size: 12px;
            color: var(--kc-destructive);
        }

        .kc-form-field__hint {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--kc-text-muted);
        }

        .kc-picture-preview {
            margin-top: 8px;
            max-width: 160px;
            max-height: 120px;
            border-radius: var(--kc-radius-input);
            border: 1px solid var(--kc-border);
            object-fit: cover;
        }

        .kc-grid-empty {
            margin: 0;
            font-size: 13px;
            color: var(--kc-text-muted);
        }

        .kc-child-grid-wrap {
            overflow-x: auto;
            border: 1px solid var(--kc-border);
            border-radius: 8px;
        }

        .kc-child-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .kc-child-grid th {
            text-align: left;
            padding: 8px 10px;
            background: #f9fafb;
            color: var(--kc-text-muted);
            font-weight: 600;
            white-space: nowrap;
            border-bottom: 1px solid var(--kc-border);
        }

        .kc-child-grid td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--kc-border);
            min-width: 120px;
        }

        .kc-child-grid tbody tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 767px) {
            .kc-form-grid {
                grid-template-columns: 1fr;
            }

            .kc-form-field--span2 {
                grid-column: span 1;
            }
        }
    </style>
</div>
