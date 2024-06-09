@extends('layouts.admin.app')

@section('title', translate('POS Orders'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style type="text/css" media="print">
        @page {
            size: auto;
            /* auto is the initial value */
            margin: 0;
            /* this affects the margin in the printer settings */
        }
    </style>
@endpush


@section('content')
    <!-- ========================= SECTION CONTENT ========================= -->
    <section class="section-content padding-y-sm bg-default mt-1">
        <div class="content container-fluid">
            <div class="d-flex flex-wrap">
                <div class="order--pos-left">
                    <div class="card h-100">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title">
                                <span class="card-header-icon">
                                    <i class="tio-incognito"></i>
                                </span>
                                <span>
                                    {{ translate('product_section') }}
                                </span>
                            </h5>
                        </div>
                        <div class="card-header">
                            <div class="w-100">
                                <div class="row g-2 justify-content-around">
                                    <div class="col-sm-6 col-12">
                                        <select name="store_id" id="store_select" data-url="{{ url()->full() }}"
                                            data-filter="store_id"
                                            data-placeholder="{{ translate('messages.select_store') }}"
                                            class="js-data-example-ajax form-control h--45px set-filter">
                                            @if ($store)
                                                <option value="{{ $store->id }}" selected>{{ $store->name }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="col-sm-6 col-12">
                                        <select name="category" id="category"
                                            class="form-control js-select2-custom mx-1 set-filter"
                                            data-url="{{ url()->full() }}" data-filter="category_id"
                                            title="{{ translate('messages.select_category') }}" disabled>
                                            <option value="">{{ translate('messages.all_categories') }}</option>
                                            @foreach ($categories as $item)
                                                <option value="{{ $item->id }}"
                                                    {{ $category == $item->id ? 'selected' : '' }}>
                                                    {{ Str::limit($item->name, 20, '...') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-12 col-12 d-flex">
                                        <form id="search-form" class="search-form col-6">
                                            <!-- Search -->
                                            <div class="input-group input--group">
                                                <input id="datatableSearch" type="search" value="{{ $keyword ?? '' }}"
                                                    name="search" class="form-control h--45px"
                                                    placeholder="{{ translate('messages.ex_:_search_here') }}"
                                                    aria-label="{{ translate('messages.search_here') }}" disabled>
                                                <button type="submit" class="btn btn--secondary h--45px">
                                                    <i class="tio-search"></i>
                                                </button>
                                            </div>
                                            <!-- End Search -->
                                        </form>
                                        <div class="col-sm-6 col-12">
                                            <select name="order_by" id="order_by"
                                                class="form-control js-select2-custom mx-1 set-filter"
                                                data-url="{{ url()->full() }}" data-filter="order_by"
                                                title="{{ translate('messages.select_order_by') }}">
                                                <option value="">{{ translate('messages.all_order_by') }}</option>
                                                @foreach ($order_types as $order)
                                                    <option value="{{ $order }}"
                                                        {{ $order_by == $order ? 'selected' : '' }}>
                                                        {{ Str::limit($order, 20, '...') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="card-body d-flex flex-column" id="items">
                            <div class="row mb-5">
                                <div class="col-12 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title text-primary">
                                            <span class="card-header-icon">
                                                <i class="tio-pen"></i>
                                            </span>
                                            <span>{{ translate('messages.write_your_order') }}</span>
                                        </h5>
                                    </div>
                                </div>
                                <textarea name="order_note" class="form-control" placeholder="{{ translate('messages.order_note') }}" id="order_note"
                                    cols="30" rows="10">{{ old('order_note') }}</textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title text-primary">
                                            <span class="card-header-icon">
                                                <i class="tio-shopping-cart"></i>
                                            </span>
                                            <span>{{ translate('messages.choose_your_product') }}</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mb-auto">
                                @foreach ($products as $product)
                                    <div class="order--item-box item-box">
                                        @include('admin-views.pos._single_product', [
                                            'product' => $product,
                                            'store_data' => $store,
                                            'order_by' => request()->query('order_by'),
                                        ])
                                    </div>
                                @endforeach
                            </div>
                            @if (count($products) === 0)
                                <div class="search--no-found">
                                    <img src="{{ asset('public/assets/admin/img/search-icon.png') }}" alt="img">
                                    <p>
                                        {{ translate('messages.no_products_on_pos_search') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer border-0">
                            {!! $products->withQueryString()->links() !!}
                        </div>
                    </div>
                </div>
                <div class="order--pos-right">
                    <div class="card h-100">
                        <div class="card-header bg-light border-0 m-1">
                            <h5 class="card-title">
                                <span class="card-header-icon">
                                    <i class="tio-money-vs"></i>
                                </span>
                                <span>
                                    {{ translate('billing_section') }}
                                </span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                                <div class="d-flex flex-wrap flex-row p-2 add--customer-btn">
                                    <select id="customer" name="customer_id"
                                        data-placeholder="{{ translate('messages.select_customer') }}"
                                        class="js-data-example-ajax form-control">
                                    </select>
                                    <button class="btn btn--primary rounded font-regular" id="add_new_customer"
                                        type="button" data-toggle="modal" data-target="#add-customer" title="Add Customer">
                                        <i class="tio-add-circle-outlined"></i> {{ translate('Add new customer') }}
                                    </button>
                                </div>

                            <div class="pos--delivery-options">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title">
                                        <span class="card-title-icon">
                                            <i class="tio-user"></i>
                                        </span>
                                        <span>{{ translate('Delivery Information') }}
                                            <small>({{ translate('Home Delivery') }})</small>
                                        </span>
                                    </h5>
                                    <span class="delivery--edit-icon text-primary" id="delivery_address"
                                        data-toggle="modal" data-target="#deliveryAddrModal">
                                        <i class="tio-edit"></i>
                                    </span>
                                </div>
                                <div class="pos--delivery-options-info d-flex flex-wrap" id="del-add">
                                    @include('admin-views.pos._address')
                                </div>
                            </div>
                            <div class='w-100' id="cart">
                                @include('admin-views.pos._cart', [
                                    'store' => $store,
                                    'blocks' => $blocks,
                                    'order_by' => request()->query('order_by'),
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- container //  -->
    </section>

    <!-- End Content -->
    <div class="modal fade" id="quick-view" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" id="quick-view-modal">

            </div>
        </div>
    </div>

    <div class="modal fade" id="print-invoice" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.print_invoice') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body row ff-emoji">
                    <div class="col-md-12">
                        <div class="text-center">
                            <input type="button" class="btn btn--primary non-printable text-white print-Div"
                                value="{{ translate('Proceed, If thermal printer is ready.') }}" />
                            <a href="{{ url()->previous() }}" class="btn btn-danger non-printable">
                                {{ translate('messages.back') }}
                            </a>
                        </div>
                        <hr class="non-printable">
                    </div>
                    <div class="row m-auto" id="print-modal-content">

                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="add-customer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('add_new_customer') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('admin.pos.customer-store') }}" method="post" id="product_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 col-lg-12">
                                <div class="form-group">
                                    <label for="f_name" class="input-label">{{ translate('name') }} <span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input id="f_name" type="text" name="f_name" class="form-control"
                                        value="{{ old('f_name') }}" placeholder="{{ translate('name') }}" required>
                                </div>
                            </div>
                            {{-- <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="l_name" class="input-label">{{ translate('last_name') }} <span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input id="l_name" type="text" name="l_name" class="form-control"
                                        value="{{ old('l_name') }}" placeholder="{{ translate('last_name') }}">
                                </div>
                            </div> --}}
                        </div>
                        <div class="row">
                            {{-- <div class="col-12 col-lg-6">
                                <div class="form-group">
                                    <label for="email" class="input-label">{{ translate('email') }}<span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input id="email" type="email" name="email" class="form-control"
                                        value="{{ old('email') }}"
                                        placeholder="{{ translate('Ex_:_ex@example.com') }}">
                                </div>
                            </div> --}}
                            <div class="col-12 col-lg-12">
                                <div class="form-group">
                                    <label for="phone" class="input-label">{{ translate('phone') }}
                                        ({{ translate('with_country_code') }})<span
                                            class="input-label-secondary text-danger">*</span></label>
                                    <input id="phone" type="text" name="phone" class="form-control"
                                        value="{{ old('phone') }}" placeholder="{{ translate('phone') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end">
                            <button type="reset" class="btn btn--reset">{{ translate('reset') }}</button>
                            <button type="submit" id="submit_new_customer"
                                class="btn btn--primary">{{ translate('submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection


@push('script_2')
    {{-- <script
        src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value }}&libraries=places&callback=initMap&v=3.49">
    </script> --}}

    <script src="{{ asset('public/assets/admin/js/view-pages/pos.js') }}"></script>

    <script>
        "use strict";
        $(document).on('click', '.place-order-submit', function(event) {
            event.preventDefault();
            let customer_id = document.getElementById('customer');
            if (customer_id.value) {
                document.getElementById('customer_id').value = customer_id.value;
                let form = document.getElementById('order_place');
                form.submit();
            } else {
                toastr.error('{{ translate('messages.customer_not_selected') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        });

        $(function() {
            const searchInput = $('#searchInput');
            const suggestionsDiv = $('#suggestions');

            searchInput.on('input', handleInput);

            function handleInput() {
                const searchTerm = $(this).val().trim();

                if (searchTerm !== '') {
                    $('#delivery_fee').val(0);
                    $('#delivery_fee_span').text(0);
                    $('#latitude').val('');
                    $('#longitude').val('');
                    const apiKey = 'rKdZVKfHAvnCHfGP5BoLt0SRhHxT-NMNoSdpXlHHpPk';
                    const apiUrl =
                        `https://autosuggest.search.hereapi.com/v1/autosuggest?apiKey=${apiKey}&q=${searchTerm}&at=26.13868,50.56178`;

                    $.getJSON(apiUrl)
                        .done(renderSuggestionsOptions)
                        .fail((jqXHR, textStatus, error) => console.error('Error fetching suggestions:', error));
                } else {
                    clearSuggestions();
                }
            }

            function renderSuggestionsOptions(data) {
                suggestionsDiv.empty(); // Clear previous suggestions

                $.each(data.items, function(index, item) {
                    const suggestion = $('<div>').addClass('suggestion').text(item.address.label);
                    suggestion.on('click', function() {
                        searchInput.val(item.address.label);
                        suggestionsDiv.empty(); // Clear suggestions
                        // Call location details API or any other action here
                        let lat = item.position.lat;
                        let lng = item.position.lng;
                        let latValue = $('#latitude').val(lat);
                        let lngValue = $('#longitude').val(lng);
                        getDeliveryFeeByGoogleSearch();
                    });
                    suggestionsDiv.append(suggestion);
                });
            }

            function clearSuggestions() {
                suggestionsDiv.empty(); // Clear suggestions
            }
        });

        $(document).on('ready', function() {
            $('#store_select').select2({
                ajax: {
                    url: '{{ url('/') }}/admin/store/get-stores',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            module_id: {{ Config::get('module.current_module_id') }},
                            page: params.page
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });
        });



        $('#search-form').on('submit', function(e) {
            e.preventDefault();
            let keyword = $('#datatableSearch').val();
            let url = new URL('{!! url()->full() !!}');
            url.searchParams.set('keyword', keyword);
            location.href = url;
        });

        $(document).on('click', '.quick-View', function() {
            $.get({
                url: '{{ route('admin.pos.quick-view') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id')
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });



        $(document).on('click', '.quick-View-Cart-Item', function() {
            $.get({
                url: '{{ route('admin.pos.quick-view-cart-item') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('product-id'),
                    item_key: $(this).data('item-key'),
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });


        function checkAddToCartValidity() {
            let names = {};
            $('#add-to-cart-form input:radio').each(function() {
                names[$(this).attr('name')] = true;
            });
            let count = 0;
            $.each(names, function() {
                count++;
            });
            if ($('input:radio:checked').length === count) {
                return true;
            }
            return true;
        }

        function checkStore() {
            let module_id = {{ Config::get('module.current_module_id') }};
            let store_id = getUrlParameter('store_id');
            if (module_id && store_id) {
                $('#category').prop("disabled", false);
                $('#datatableSearch').prop("disabled", false);
            }
        }

        checkStore();

        function getVariantPrice() {

            if ($('#add-to-cart-form input[name=quantity]').val() > 0 && checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: '{{ route('admin.pos.variant_price') }}',
                    data: $('#add-to-cart-form').serializeArray(),
                    success: function(data) {
                        if (data.error === 'quantity_error') {
                            toastr.error(data.message);
                        } else {
                            $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                            $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                        }
                    }
                });
            }
        }


        $(document).on('click', '.add-To-Cart', function() {

            if (checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                let form_id = 'add-to-cart-form'
                $.post({
                    url: '{{ route('admin.pos.add-to-cart') }}',
                    data: $('#' + form_id).serializeArray(),
                    beforeSend: function() {
                        $('#loading').show();
                    },
                    success: function(data) {
                        if (data.data === 1) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Cart',
                                text: "{{ translate('messages.product_already_added_in_cart') }}"
                            });
                            return false;
                        } else if (data.data === 2) {
                            updateCart();
                            Swal.fire({
                                icon: 'info',
                                title: 'Cart',
                                text: "{{ translate('messages.product_has_been_updated_in_cart') }}"
                            });

                            return false;
                        } else if (data.data === 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Cart',
                                text: '{{ translate('Sorry, product out of stock') }}.'
                            });
                            return false;
                        } else if (data.data === -1) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Cart',
                                text: '{{ translate('Sorry, you can not add multiple stores data in same cart') }}.'
                            });
                            return false;
                        } else if (data.data === 'variation_error') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Cart',
                                text: data.message
                            });
                            return false;
                        }
                        $('.call-when-done').click();

                        toastr.success('{{ translate('messages.product_has_been_added_in_cart') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });

                        updateCart();
                    },
                    complete: function() {
                        $('#loading').hide();
                    }
                });
            } else {
                Swal.fire({
                    type: 'info',
                    title: '{{ translate('Cart') }}',
                    text: '{{ translate('Please choose all the options') }}'
                });
            }

        });


        $(document).on('click', '.delivery-Address-Store', function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            let form_id = 'delivery_address_store';
            $.post({
                url: '{{ route('admin.pos.add-delivery-address') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $('#del-add').empty().html(data.view);
                    }
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    $('#loading').hide();
                    $('#deliveryAddrModal').modal('hide');
                }
            });

        });

        $(document).on('click', '.remove-From-Cart', function() {
            let key = $(this).data('product-id')
            $.post('{{ route('admin.pos.remove-from-cart') }}', {
                _token: '{{ csrf_token() }}',
                key: key
            }, function(data) {
                if (data.errors) {
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    updateCart();
                    toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }

            });
        });

        $(document).on('click', '.empty-Cart', function() {
            $.post('{{ route('admin.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }, function() {
                $('#del-add').empty();
                updateCart();
                toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            });
        });


        function updateCart() {
            $.post('<?php echo e(route('admin.pos.cart_items')); ?>?store_id={{ request()?->store_id }}&order_by={{ request()?->order_by }}', {
                _token: '<?php echo e(csrf_token()); ?>'
            }, function(data) {
                $('#cart').empty().html(data);
            });
        }

        $(function() {
            $(document).on('click', 'input[type=number]', function() {
                this.select();
            });
        });


        $(document).on('change', '.update-Quantity', function(event) {

            let element = $(event.target);
            let minValue = parseInt(element.attr('min'));
            let maxValue = parseInt(element.attr('max'));
            let valueCurrent = parseInt(element.val());

            let key = element.data('key');

            if (valueCurrent >= minValue && valueCurrent <= maxValue) {
                $.post('{{ route('admin.pos.updateQuantity') }}', {
                    _token: '{{ csrf_token() }}',
                    key: key,
                    quantity: valueCurrent
                }, function() {
                    updateCart();
                });
            } else if (valueCurrent > maxValue) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cart',
                    text: 'Sorry, cart limit exceeded.'
                });
                element.val(element.data('oldValue'));
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Cart',
                    text: '{{ translate('Sorry, the minimum value was reached') }}'
                });
                element.val(element.data('oldValue'));
            }


            // Allow: backspace, delete, tab, escape, enter and .
            if (event.type === 'keydown') {
                if ($.inArray(event.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (event.keyCode === 65 && event.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (event.keyCode >= 35 && event.keyCode <= 39)) {
                    // let it happen, don't do anything
                    return;
                }
                // Ensure that it is a number and stop the keypress
                if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event
                        .keyCode > 105)) {
                    event.preventDefault();
                }
            }

        });


        $('#customer').select2({
            ajax: {
                url: '{{ route('admin.pos.customers') }}',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

        function print_invoice(order_id) {
            $.get({
                url: '{{ url('/') }}/admin/pos/invoice/' + order_id,
                dataType: 'json',
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    $('#print-invoice').modal('show');
                    $('#print-modal-content').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        }
        @if (session('last_order'))
            $(document).on('ready', function() {
                $('#print-invoice').modal('show');
            });
            print_invoice("{{ session('last_order') }}")
            @php(session(['last_order' => false]))
        @endif
    </script>
@endpush
