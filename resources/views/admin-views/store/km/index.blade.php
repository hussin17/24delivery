@extends('layouts.admin.app')

@section('title', translate('Zone Wise Module Setup'))

@push('css_or_js')
@endpush

@section('content')

    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--26" alt="">
                </span>
                @if (Request::query('id'))
                    <span>
                        {{ translate('edit_kms') }} {{ isset($km->from) ? '( ' . $km->from . ' - ' . $km->to . ' )' : '' }}
                    </span>
                @else
                    <span>
                        {{ translate('add_kms') }}
                        {{ isset($store_kms->from) && isset($store_kms->to) ? '( ' . $store_kms->from . ' - ' . $store_kms->to . ' )' : '' }}
                    </span>
                @endif
            </h1>
        </div>
        <!-- End Page Header -->
        <form
            action="{{ Request::query('id') ? route('admin.store.kms.update', Request::query('id')) : route('admin.store.kms.store') }}"
            method="post" id="zone_form" class="shadow--card">
            @csrf
            @if (Request::query('id'))
                @method('PUT')
            @endif
            <input type="hidden" name="store_id" value="{{ Request::query('store_id') }}">
            <div class="row g-2">

                <div class="col-md-12">
                    <div class="delivery_charge_options" id="delivery_charge_options">
                        <div class="row gy-1" id="mod-label">
                            <div class="col-sm-4">
                                <label for="">{{ translate('messages.From') }}</label>
                            </div>
                            <div class="col-sm-4">
                                <label for="">{{ translate('messages.charge value') }}
                                    ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    <span class="input-label-secondary text-danger">*</span>
                                </label>
                            </div>
                        </div>

                        <div class="row gy-1 module-row" id="module_">
                            <div class="col-sm-2">
                                @if (Request::query('id'))
                                    <input class="form-control" name="from" value="{{ $km->from }}"
                                        placeholder="{{ translate('messages.from') }}">
                                @else
                                    <input class="form-control" name="from"
                                        placeholder="{{ translate('messages.from') }}">
                                @endif
                            </div>
                            <div class="col-sm-2">
                                @if (Request::query('id'))
                                    <input class="form-control" name="to" value="{{ $km->to }}"
                                        placeholder="{{ translate('messages.to') }}">
                                @else
                                    <input class="form-control" name="to"
                                        placeholder="{{ translate('messages.to') }}">
                                @endif
                            </div>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="shipping_price"
                                    value="{{ $km->shipping_price ?? '' }}"
                                    placeholder="{{ translate('Set charge price') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container mt-3 justify-content-end">
                @if (Request::query('id'))
                    <button type="submit" class="btn btn-soft-info">{{ translate('messages.update') }}</button>
                @else
                    <button type="submit" class="btn btn--primary">{{ translate('messages.add') }}</button>
                @endif
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive datatable-custom">
            <table id="columnSearchDatatable"
                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                data-hs-datatables-options='{
                        "order": [],
                        "orderCellsTop": true,
                        "paging":false
                        }'>
                <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{ translate('messages.SL') }}</th>
                        <th class="border-0">{{ translate('messages.from') }} - {{ translate('messages.to') }}</th>
                        <th class="border-0">{{ translate('messages.shipping value') }}</th>
                        <th class="border-0 text-center">{{ translate('messages.action') }}</th>
                    </tr>
                </thead>

                <tbody id="set-rows">
                    @php($non_mod = 0)
                    @foreach ($store_kms as $key => $item)
                        {{-- @php($non_mod = count($item->modules) > 0 && $non_mod == 0 ? $non_mod : $non_mod + 1) --}}
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>
                                <span class="d-block font-size-sm text-body">
                                    {{ $item['from'] }} - {{ $item['to'] }}
                                </span>
                            </td>
                            <td>
                                <span class="d-block font-size-sm text-body">
                                    {{ $item['shipping_price'] }}
                                </span>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                        href="{{ route('admin.store.kms.index', ['store_id' => Request::query('store_id'), 'id' => $item['id']]) }}"
                                        title="{{ translate('messages.edit_kms') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn btn--danger btn-outline-danger status_form_alert"
                                        href="javascript:" data-id="kms-{{ $item['id'] }}"
                                        data-title="{{ translate('Want_to_Delete_this_kms?') }}"
                                        title="{{ translate('messages.delete_kms') }}">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.store.kms.destroy', [$item['id']]) }}" method="post"
                                        id="kms-{{ $item['id'] }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (count($store_kms) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $store_kms->withQueryString()->links() !!}
            </div>
            @if (count($store_kms) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>
                        {{ translate('no_data_found') }}
                    </h5>
                </div>
            @endif
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/tags-input.min.js"></script>
    <script>
        "use strict";

        $(document).on('ready', function() {
            $("#increased_delivery_fee_status").on('change', function() {
                if ($("#increased_delivery_fee_status").is(':checked')) {
                    $('#increased_delivery_fee').removeAttr('readonly');
                    $('#increase_delivery_charge_message').removeAttr('readonly');
                } else {
                    $('#increased_delivery_fee').attr('readonly', true);
                    $('#increase_delivery_charge_message').attr('readonly', true);
                    $('#increased_delivery_fee').val('Ex : 0');
                    $('#increase_delivery_charge_message').val('');
                }
            });
        });


        let modules = <?php echo json_encode($store_kms); ?>;
        let mod = {{ count($store_kms) }};
        if (mod > 0) {
            $('#mod-label').show();
        } else {
            $('#mod-label').hide();
        }
        $('#choice_modules').on('change', function() {
            $('#mod-label').show();
            let ids = $('.module-row').map(function() {
                return $(this).attr('id').split('_')[1];
            }).get();

            $.each($("#choice_modules option:selected"), function(index, element) {
                console.log($(this).val());
                if (ids.includes($(this).val())) {
                    ids = ids.filter(id => id !== $(this).val());
                } else {
                    let name = $('#choice_modules option[value="' + $(this).val() + '"]').html();
                    let found = modules.find(modul => modul.id == $(this).val());
                    if (found.module_type == 'parcel') {

                        add_parcel_module($(this).val(), name.trim());
                    } else {

                        add_more_delivery_charge_option($(this).val(), name.trim());
                    }
                }
            });
            console.log(ids)
            if (ids.length > 0) {
                ids.forEach(element => {
                    console.log("module_", 3)
                    $("#module_" + element.trim()).remove();
                });
            }
        });

        function add_more_delivery_charge_option(i, name) {
            let n = name;
            $('#delivery_charge_options').append(
                '<div class="row gy-1 module-row" id="module_' + i +
                '"><div class="col-sm-4"><input type="text" class="form-control" value="' + n +
                '" placeholder="{{ translate('messages.choice_title') }}" readonly></div><div class="col-sm-2"><input type="number" class="form-control" name="module_data[' +
                i +
                '][shipping_price]" step=".01" min="0" placeholder="{{ translate('messages.enter_Amount') }}" title="{{ translate('messages.per_km_delivery_charge') }}" required></div><div class="col-sm-2"><input type="number" step=".01" min="0" class="form-control" name="module_data[' +
                i +
                '][minimum_shipping_charge]" placeholder="{{ translate('messages.enter_Amount') }}" title="{{ translate('messages.Minimum delivery charge') }}" required></div><div class="col-sm-2"><input type="number" step=".01" min="0" class="form-control" name="module_data[' +
                i +
                '][maximum_shipping_charge]" placeholder="{{ translate('messages.enter_Amount') }}" title="{{ translate('messages.maximum delivery charge') }}"></div><div class="col-sm-2"><input type="number" step=".01" min="0" class="form-control" name="module_data[' +
                i +
                '][maximum_cod_order_amount]" placeholder="{{ translate('enter_Amount') }}" title="{{ translate('set_maximum_cod_order_amount') }}"></div></div>'
            );
        }

        function add_parcel_module(i, name) {
            let n = name;
            $('#delivery_charge_options').append(
                '<div class="row gy-1 module-row" id="module_' + i +
                '"><div class="col-sm-4"><input type="text" class="form-control" value="' + n +
                '" placeholder="{{ translate('messages.choice_title') }}" readonly></div><div class="col-sm-2"><input type="number" name="module_data[' +
                i +
                '][shipping_price]" class="form-control" step=".01" min="0" placeholder="{{ translate('Set charge from parcel category') }}" value="" title="{{ translate('messages.per_km_delivery_charge') }}" readonly></div><div class="col-sm-2"><input type="number" name="module_data[' +
                i +
                '][minimum_shipping_charge]" step=".01" min="0" class="form-control" placeholder="{{ translate('Set charge from parcel category') }}" value="" title="{{ translate('messages.Minimum delivery charge') }}" readonly></div><div class="col-sm-2"><input type="number" name="module_data[' +
                i +
                '][maximum_shipping_charge]" step=".01" min="0" class="form-control" placeholder="{{ translate('Set charge from parcel category') }}" value="" title="{{ translate('messages.maximum delivery charge') }}" readonly></div><div class="col-sm-2"><input type="number" step=".01" min="0" class="form-control" name="module_data[' +
                i +
                '][maximum_cod_order_amount]" placeholder="{{ translate('enter_Amount') }}" title="{{ translate('set_maximum_cod_order_amount') }}" readonly></div></div>'
            );
        }
    </script>
    <script>
        "use strict";
        $(".popover-wrapper").click(function() {
            $(".popover-wrapper").removeClass("active");
        });

        $('.status_form_alert').on('click', function(event) {
            let id = $(this).data('id');
            let title = $(this).data('title');
            let message = $(this).data('message');
            status_form_alert(id, title, message, event)
        })

        function status_form_alert(id, title, message, e) {
            e.preventDefault();
            Swal.fire({
                title: title,
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('messages.no') }}',
                confirmButtonText: '{{ translate('messages.Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $('#' + id).submit()
                }
            })
        }
        auto_grow();

        function auto_grow() {
            let element = document.getElementById("coordinates");
            element.style.height = "5px";
            element.style.height = (element.scrollHeight) + "px";
        }


        $(document).on('ready', function() {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function() {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });


            $('#column3_search').on('change', function() {
                datatable
                    .columns(2)
                    .search(this.value)
                    .draw();
            });


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function() {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });

            $("#zone_form").on('keydown', function(e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                }
            })
        });

        let map; // Global declaration of the map
        let drawingManager;
        let lastpolygon = null;
        let polygons = [];

        function resetMap(controlDiv) {
            // Set CSS for the control border.
            const controlUI = document.createElement("div");
            controlUI.style.backgroundColor = "#fff";
            controlUI.style.border = "2px solid #fff";
            controlUI.style.borderRadius = "3px";
            controlUI.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
            controlUI.style.cursor = "pointer";
            controlUI.style.marginTop = "8px";
            controlUI.style.marginBottom = "22px";
            controlUI.style.textAlign = "center";
            controlUI.title = "Reset map";
            controlDiv.appendChild(controlUI);
            // Set CSS for the control interior.
            const controlText = document.createElement("div");
            controlText.style.color = "rgb(25,25,25)";
            controlText.style.fontFamily = "Roboto,Arial,sans-serif";
            controlText.style.fontSize = "10px";
            controlText.style.lineHeight = "16px";
            controlText.style.paddingLeft = "2px";
            controlText.style.paddingRight = "2px";
            controlText.innerHTML = "X";
            controlUI.appendChild(controlText);
            // Setup the click event listeners: simply set the map to Chicago.
            controlUI.addEventListener("click", () => {
                lastpolygon.setMap(null);
                $('#coordinates').val('');

            });
        }

        function initialize() {
            @php($default_location = \App\Models\BusinessSetting::where('key', 'default_location')->first())
            @php($default_location = $default_location->value ? json_decode($default_location->value, true) : 0)
            let myLatlng = {
                lat: {{ $default_location ? $default_location['lat'] : '23.757989' }},
                lng: {{ $default_location ? $default_location['lng'] : '90.360587' }}
            };


            let myOptions = {
                zoom: 13,
                center: myLatlng,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            }
            map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);
            drawingManager = new google.maps.drawing.DrawingManager({
                drawingMode: google.maps.drawing.OverlayType.POLYGON,
                drawingControl: true,
                drawingControlOptions: {
                    position: google.maps.ControlPosition.TOP_CENTER,
                    drawingModes: [google.maps.drawing.OverlayType.POLYGON]
                },
                polygonOptions: {
                    editable: true
                }
            });
            drawingManager.setMap(map);


            //get current location block
            // infoWindow = new google.maps.InfoWindow();
            // Try HTML5 geolocation.
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        map.setCenter(pos);
                    });
            }

            drawingManager.addListener("overlaycomplete", function(event) {
                if (lastpolygon) {
                    lastpolygon.setMap(null);
                }
                $('#coordinates').val(event.overlay.getPath().getArray());
                lastpolygon = event.overlay;
                auto_grow();
            });

            const resetDiv = document.createElement("div");
            resetMap(resetDiv, lastpolygon);
            map.controls[google.maps.ControlPosition.TOP_CENTER].push(resetDiv);

            // Create the search box and link it to the UI element.
            const input = document.getElementById("pac-input");
            const searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
            // Bias the SearchBox results towards current map's viewport.
            map.addListener("bounds_changed", () => {
                searchBox.setBounds(map.getBounds());
            });
            let markers = [];
            // Listen for the event fired when the user selects a prediction and retrieve
            // more details for that place.
            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();

                if (places.length == 0) {
                    return;
                }
                // Clear out the old markers.
                markers.forEach((marker) => {
                    marker.setMap(null);
                });
                markers = [];
                // For each place, get the icon, name and location.
                const bounds = new google.maps.LatLngBounds();
                places.forEach((place) => {
                    if (!place.geometry || !place.geometry.location) {
                        console.log("Returned place contains no geometry");
                        return;
                    }
                    const icon = {
                        url: place.icon,
                        size: new google.maps.Size(71, 71),
                        origin: new google.maps.Point(0, 0),
                        anchor: new google.maps.Point(17, 34),
                        scaledSize: new google.maps.Size(25, 25),
                    };
                    // Create a marker for each place.
                    markers.push(
                        new google.maps.Marker({
                            map,
                            icon,
                            title: place.name,
                            position: place.geometry.location,
                        })
                    );

                    if (place.geometry.viewport) {
                        // Only geocodes have viewport.
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                });
                map.fitBounds(bounds);
            });
        }

        // initialize();


        function set_all_zones() {
            $.get({
                url: '{{ route('admin.zone.zoneCoordinates') }}',
                dataType: 'json',
                success: function(data) {
                    for (let i = 0; i < data.length; i++) {
                        polygons.push(new google.maps.Polygon({
                            paths: data[i],
                            strokeColor: "#FF0000",
                            strokeOpakms: 0.8,
                            strokeWeight: 2,
                            fillColor: "#FF0000",
                            fillOpakms: 0.1,
                        }));
                        polygons[i].setMap(map);
                    }

                },
            });
        }
        $(document).on('ready', function() {
            set_all_zones();
        });


        $('#zone_form').on('submit', function() {
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('admin.business-settings.zone.store') }}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    if (data.errors) {
                        $.each(data.errors, function(index, value) {
                            toastr.error(value.message);
                        });
                    } else {
                        $('.tab-content').find('input:text').val('');
                        $('input[name="name"]').val(null);
                        lastpolygon.setMap(null);
                        $('#coordinates').val(null);
                        toastr.success("{{ translate('messages.zone_added_successfully') }}", {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        $('#set-rows').html(data.view);
                        $('#itemCount').html(data.total);
                        $("#module-setup-modal-button").prop("href",
                            '{{ url('/') }}/admin/business-settings/zone/module-setup/' +
                            data.id)
                        $("#warning-modal").modal("show");
                    }
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });

        $('#reset_btn').click(function() {
            $('.tab-content').find('input:text').val('');

            lastpolygon.setMap(null);
            $('#coordinates').val(null);
        })
    </script>
@endpush
