<div class="d-flex flex-row cart--table-scroll">
    <table class="table table-bordered">
        <thead class="text-muted thead-light">
            <tr class="text-center">
                <th class="border-bottom-0" scope="col">{{ translate('messages.item') }}</th>
                <th class="border-bottom-0" scope="col">{{ translate('messages.qty') }}</th>
                <th class="border-bottom-0" scope="col">{{ translate('messages.price') }}</th>
                <th class="border-bottom-0" scope="col">{{ translate('messages.delete') }}</th>
            </tr>
        </thead>

        <tbody>
            <?php
            $subtotal = 0;
            $addon_price = 0;
            $tax = isset($store) ? $store->tax : 0;
            $discount = 0;
            $discount_type = 'amount';
            $discount_on_product = 0;
            $variation_price = 0;
            ?>
            @if (session()->has('cart') && count(session()->get('cart')) > 0)
                <?php
                $cart = session()->get('cart');
                if (isset($cart['tax'])) {
                    dd('TAX Into Cart', $cart);
                    $tax = $cart['tax'];
                }
                if (isset($cart['discount'])) {
                    $discount = $cart['discount'];
                    $discount_type = $cart['discount_type'];
                }
                ?>
                @foreach (session()->get('cart') as $key => $cartItem)
                    {{-- {{ dd(session()->get('cart')) }} --}}
                    @if (is_array($cartItem))
                        <?php
                        $variation_price += $cartItem['variation_price'] ?? 0;
                        $product_subtotal = $cartItem['price'] * $cartItem['quantity'];
                        $discount_on_product += $cartItem['discount'] * $cartItem['quantity'];
                        $subtotal += $product_subtotal;
                        $addon_price += $cartItem['addon_price'];
                        ?>
                        <tr>
                            <td class="media align-items-center cursor-pointer quick-View-Cart-Item"
                                data-product-id="{{ $cartItem['id'] }}" data-item-key="{{ $key }}">
                                <img class="avatar avatar-sm mr-1 onerror-image"
                                    src="{{ \App\CentralLogics\Helpers::onerror_image_helper(
                                        $cartItem['image'] ?? '',
                                        asset('storage/app/public/product') . '/' . $cartItem['image'] ?? '',
                                        asset('public/assets/admin/img/100x100/2.png'),
                                        'product/',
                                    ) }}"
                                    data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                    alt="{{ $cartItem['name'] }} image">
                                <div class="media-body">
                                    <h5 class="text-hover-primary mb-0">{{ Str::limit($cartItem['name'], 10) }}</h5>
                                    <small>{{ Str::limit($cartItem['variant'], 20) }}</small>
                                </div>
                            </td>
                            <td class="text-center middle-align">
                                <input type="number" data-key="{{ $key }}"
                                    class="amount--input form-control text-center update-Quantity"
                                    value="{{ $cartItem['quantity'] }}" min="1"
                                    max="{{ $cartItem['maximum_cart_quantity'] ?? '9999999999' }}">
                            </td>
                            <td class="text-center px-0 py-1">
                                <div class="btn">
                                    {{ \App\CentralLogics\Helpers::format_currency($product_subtotal) }}
                                </div> <!-- price-wrap .// -->
                            </td>
                            <td class="align-items-center text-center">
                                <a href="javascript:" data-product-id="{{ $key }}"
                                    class="btn btn-sm btn-outline-danger remove-From-Cart"> <i
                                        class="tio-delete-outlined"></i></a>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
        </tbody>
    </table>
</div>

<?php
if (session()->get('address') && count(session()->get('address')) > 0) {
    $delivery_fee = session()->get('address')['delivery_fee'];
} else {
    $delivery_fee = 0;
}
$total = $subtotal + $addon_price;
$discount_amount = $discount_type == 'percent' && $discount > 0 ? (($total - $discount_on_product) * $discount) / 100 : $discount;
$total -= $discount_amount + $discount_on_product;
$tax_included = \App\Models\BusinessSetting::where(['key' => 'tax_included'])->first() ? \App\Models\BusinessSetting::where(['key' => 'tax_included'])->first()->value : 0;
$total_tax_amount = $tax > 0 ? ($total * $tax) / 100 : 0;
$total = $total + $delivery_fee;
?>
<div class="box p-3">
    <dl class="row text-sm-right">

        <dt class="col-sm-6">{{ translate('messages.addon') }}:</dt>
        <dd class="col-sm-6 text-right">{{ \App\CentralLogics\Helpers::format_currency($addon_price) }}</dd>

        <dt class="col-sm-6">{{ translate('messages.subtotal') }}
            @if ($tax_included == 1)
                ({{ translate('messages.TAX_Included') }})
                @php($total_tax_amount = 0)
            @endif
            :
        </dt>
        <dd class="col-sm-6 text-right">{{ \App\CentralLogics\Helpers::format_currency($subtotal + $addon_price) }}
        </dd>


        <dt class="col-sm-6">{{ translate('messages.discount') }} :</dt>
        <dd class="col-sm-6 text-right">-
            {{ \App\CentralLogics\Helpers::format_currency(round($discount_on_product, 2)) }}</dd>
        <dt class="col-6">{{ translate('messages.delivery_fee') }} :</dt>
        <dd class="col-6 text-right" id="delivery_price">
            {{ \App\CentralLogics\Helpers::format_currency($delivery_fee) }}</dd>
        @if ($tax_included != 1)
            <dt class="col-sm-6">{{ translate('messages.tax') }} : </dt>
            <dd class="col-sm-6 text-right">
                {{ \App\CentralLogics\Helpers::format_currency(round($total_tax_amount, 2)) }}</dd>
        @endif
        <dt class="col-6 pr-0">
            <hr class="mt-0">
        </dt>
        <dt class="col-6 pl-0">
            <hr class="mt-0">
        </dt>
        <dt class="col-sm-6">{{ translate('messages.total') }} : </dt>
        <dd class="col-sm-6 text-right">
            {{ \App\CentralLogics\Helpers::format_currency(round($total + $total_tax_amount, 2)) }}
        </dd>

    </dl>
    <form action="{{ route('admin.pos.order') }}?store_id={{ request('store_id') }}" id='order_place' method="post">
        @csrf
        <input type="hidden" name="user_id" id="customer_id">

        <div class="pos--payment-options mt-3 mb-3">
            @if (request()->query('order_by') == 'customer')
                <h5 class="mb-3">{{ translate('Payment Method') . ' ( Customer Mode ) ' }}</h5>
                {{-- if type is customer --}}
                <ul id="customer_payment_method">
                    @php($cod = \App\CentralLogics\Helpers::get_business_settings('cash_on_delivery'))
                    @if ($cod['status'])
                        <li>
                            <label>
                                <input type="radio" name="type" value="cash" hidden checked>
                                <span>{{ translate('Cash On Delivery') }}</span>
                            </label>
                        </li>
                    @endif
                    @php($wallet = \App\CentralLogics\Helpers::get_business_settings('wallet_status'))
                    @if ($wallet)
                        <li>
                            <label>
                                <input type="radio" name="type" value="wallet" hidden
                                    {{ $cod['status'] ? '' : 'checked' }}>
                                <span>{{ translate('Wallet') }}</span>
                            </label>
                        </li>
                    @endif
                </ul>
            @endif
            {{-- if type is store --}}
            {{-- {{ print_r(request()->all()) }} --}}

            @if (request()->query('order_by') == 'store')
                <br>
                <h5 class="mb-3">{{ translate('Payment Method') . ' ( Store Mode ) ' }}</h5>
                <ul id="store_payment_method">
                    <li>
                        <label>
                            <input type="radio" name="type" value="unpaid"
                                onclick="$('#price_from_customer_div').removeAttr('hidden')" hidden checked>
                            <span>{{ translate('unpaid') }}</span>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="type" value="paid"
                                onclick="$('#price_from_customer_div').attr('hidden', true)" hidden checked>
                            <span>{{ translate('paid') }}</span>
                        </label>
                    </li>
                    {{-- </ul> --}}
                    <div class="form-group col-12 mt-2" id="price_from_customer_div" hidden>
                        <label class="input-label" for="price_from_customer">
                            {{ translate('messages.price_from_customer') }}
                        </label>
                        <input type="number" step="0.001" placeholder="{{ translate('Enter Amount') }}"
                            min="0" step="0.001" class="form-control" name="price_from_customer"
                            id="price_from_customer">
                    </div>
                </ul>
            @endif

        </div>
        @isset($store->pre_order)
            @if ($store->pre_order == 1)
                <div class="pos--payment-options mt-3 mb-3">
                    <h5 class="mb-3">{{ translate('Order Mode') }}</h5>
                    <ul>
                        <li>
                            <label>
                                <input onclick="hideDateTime(this.value)" type="radio" name="order_mode" value="now"
                                    hidden checked>
                                <span>{{ translate('Now') }}</span>
                            </label>
                        </li>
                        <li>
                            <label>
                                <input onclick="hideDateTime(this.value)" type="radio" name="order_mode" hidden
                                    value="pre_order">
                                <span>{{ translate('messages.pre_order') }}</span>
                            </label>
                        </li>
                        <li class="col-12  ml-1 mt-2 g-2" id="datetime" style="display: none">
                            <input type="datetime-local" class="form-control" name="pre_order_datetime"
                                min="{{ date('Y-m-d\TH:i') }}">
                        </li>
                    </ul>
                </div>
            @endif
        @endisset

        <div class="row button--bottom-fixed g-1 bg-white">
            <div class="col-sm-6">
                <button type="submit"
                    class="btn  btn--primary btn-sm place-order-submit btn-block">{{ translate('messages.place_order') }}
                </button>
            </div>
            <div class="col-sm-6">
                <a href="#"
                    class="btn btn--reset btn-sm btn-block empty-Cart">{{ translate('Clear Cart') }}</a>
            </div>
        </div>
    </form>
</div>


<div class="modal fade" id="deliveryAddrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title flex-grow-1 text-center">{{ translate('delivery_options') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            @if ($store && $order_by)
                <div class="modal-body">

                    <?php
                    if (session()->has('address')) {
                        $old = session()->get('address');
                    } else {
                        $old = null;
                    }
                    ?>
                    <form id='delivery_address_store'>
                        @csrf
                        <div class="row g-2" id="delivery_address">
                            <div class="col-md-6">
                                <label class="input-label"
                                    for="contact_person_name">{{ translate('messages.contact_person_name') }}<span
                                        class="input-label-secondary text-danger">*</span></label>
                                <input id="contact_person_name" type="text" class="form-control"
                                    name="contact_person_name" value="{{ $old ? $old['contact_person_name'] : '' }}"
                                    placeholder="{{ translate('messages.Ex :') }} Jhone">
                            </div>
                            <div class="col-md-6">
                                <label class="input-label"
                                    for="contact_person_number">{{ translate('Contact Number') }}<span
                                        class="input-label-secondary text-danger">*</span></label>
                                <input id="contact_person_number" type="tel" class="form-control"
                                    name="contact_person_number"
                                    value="{{ $old ? $old['contact_person_number'] : '' }}"
                                    placeholder="{{ translate('messages.Ex :') }} +3264124565">
                            </div>

                            <div
                                style="display:flex; width: fit-content; padding: 5px 30px; background: #EEE; margin: 0 auto;
                                text-align: center; border-radius: 5pt;">
                                <span class="d-inline-block" id="delivery_fee_span">{{-- {{ $old ? $old['delivery_fee'] : 0 }} --}}</span>
                                <input type="text" name="delivery_fee" id="delivery_fee" hidden>
                                <input type="text" name="distance" id="distance" hidden>
                                <strong>
                                    {{ \App\CentralLogics\Helpers::currency_symbol() }}
                                </strong>
                            </div>

                            <div class="col-12">
                                <div class="custom-control custom-switch">
                                    <input dir="rtl" type="checkbox" name="use_google_map"
                                        class="custom-control-input" onclick="showMap(this.checked)"
                                        id="customSwitch1">
                                    <label class="custom-control-label" for="customSwitch1">
                                        {{ translate('messages.HereWeGo Map') }}
                                    </label>
                                </div>
                            </div>

                            {{-- HERE WE GO FOR Map --}}
                            <div class="col-12" style="display: none;" id="mapDiv">
                                <div class="d-flex justify-content-between">
                                    <span class="text-primary">
                                        {{ translate('* Search the address in the map to calculate delivery fee') }}
                                    </span>
                                </div>
                                <div id="store" hidden>{{ $store }}</div>
                                <input id="searchInput" class="form-control d-block input-label "
                                    style="border: 1px solid #DDD; padding: 5px; width: 100%"
                                    title="{{ translate('messages.search_your_location_here') }}" type="text"
                                    placeholder="{{ translate('messages.search_here') }}" list="suggestions">
                                <div id="suggestions"></div>
                                <div class="mb-2 h-200px" id="map" hidden></div>
                            </div>

                            {{-- GET Blocks --}}
                            <div class="col-md-12" id="blocksDiv">
                                <label class="input-label" for="block_id">{{ translate('messages.Blocks') }}
                                </label>

                                <select id="block_id" class="form-control" name="block_id"
                                    onchange="sendDataToMap(); getDeliveryFee(); resetDeliveryForm();">
                                    <option value="" selected>{{ translate('messages.please_choose_option') }}
                                    </option>
                                    @foreach ($blocks as $bl)
                                        <option value="{{ $bl->id }}">
                                            {{ trim($bl['city'] . ' ' . ($bl['block'] ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- @if ($store->wiseType == 'city') --}}
                            <div class="col-md-4" id="roadDiv">
                                <label class="input-label" for="road">{{ translate('messages.Road') }}
                                    {{-- <span class="input-label-secondary text-danger">*</span> --}}
                                </label>
                                <input id="road" type="text" class="form-control" name="road"
                                    value="{{ $old ? $old['road'] : '' }}" onkeyup="sendDataToMap()"
                                    placeholder="{{ translate('messages.Ex :') }} 4th">
                            </div>
                            <div class="col-md-4" id="houseDiv">
                                <label class="input-label" for="house">{{ translate('messages.House') }}
                                    {{-- <span class="input-label-secondary text-danger">*</span></label> --}}
                                    <input id="house" type="text" class="form-control"
                                        onkeyup="sendDataToMap()" name="house"
                                        value="{{ $old ? $old['house'] : '' }}"
                                        placeholder="{{ translate('messages.Ex :') }} 45/C">
                            </div>
                            <div class="col-md-4" id="floorDiv">
                                <label class="input-label" for="floor">{{ translate('messages.Floor') }}
                                    {{-- <span class="input-label-secondary text-danger">*</span></label> --}}
                                    <input id="floor" type="text" class="form-control" name="floor"
                                        value="{{ $old ? $old['floor'] : '' }}" onkeyup="sendDataToMap()"
                                        placeholder="{{ translate('messages.Ex :') }} 1A">
                            </div>

                            {{-- @endif --}}
                            <div class="col-md-6">
                                <label class="input-label" for="longitude">{{ translate('messages.longitude') }}
                                    <span class="input-label-secondary text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="longitude" name="longitude"
                                    value="{{ $old ? $old['longitude'] : '' }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="input-label" for="latitude">{{ translate('messages.latitude') }}<span
                                        class="input-label-secondary text-danger">*</span></label>
                                <input type="text" class="form-control" id="latitude" name="latitude"
                                    value="{{ $old ? $old['latitude'] : '' }}" readonly>
                            </div>

                            <div class="col-md-12">
                                <label class="input-label "
                                    for="address">{{ translate('messages.address details') }}</label>
                                <textarea id="address" name="address" class="form-control" cols="30" rows="3"
                                    placeholder="{{ translate('messages.Ex :') }} address">{{ $old ? $old['address'] : '' }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="btn--container justify-content-end">
                                <button class="btn btn-sm btn--primary w-100 delivery-Address-Store" type="button">
                                    {{ translate('Update_Delivery address') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center">
                                @if (!$store)
                                    <h2>
                                        {{ translate('messages.please_select_a_store_first') }}
                                    </h2>
                                @endif
                                @if (!$order_by)
                                    <h2>
                                        {{ translate('messages.please_select_an_order_by') }}
                                    </h2>
                                @endif
                                <button data-dismiss="modal"
                                    class="btn btn-primary">{{ translate('messages.Ok') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>


<script src="{{ asset('public/assets/admin') }}/js/view-pages/common.js"></script>

<script>
    "use strict";

    function hideDateTime(type) {
        console.log(type);
        if (type == 'now') {
            document.getElementById('datetime').style.display = 'none';
        } else {
            document.getElementById('datetime').style.display = 'block';
        }
    }

    function resetDeliveryForm() {
        $('#delivery_fee').val(0);
        $('#delivery_fee_span').text(0);
        $('#distance').val(0);
        $('#road').val('');
        $('#house').val('');
        $('#floor').val('');
        $('#latitude').val('');
        $('#longitude').val('');
    }

    function getDeliveryFee() {
        // if Block id And Road And House Then Send Request
        let store = $('#store').html();
        let block_id = $('#block_id').val();
        let road = $('#road').val();
        let house = $('#house').val();
        let order_by = $('#order_by').val();
        let lat = $('#latitude').val();
        let lng = $('#longitude').val();
        console.log(block_id, road, house, order_by);
        // if (block_id && road && house) {
        $.get({
            url: "{{ route('admin.pos.extra_charge') }}",
            data: {
                store: store,
                block_id: block_id,
                order_by: order_by,
                lat: lat,
                lng: lng
            }
        }).done(function(response) {
            // Handle the response
            console.log(response);
            $('#delivery_fee_span').text(
                Number(response.shipping_price) +
                Number(response.extra_charge ? response.extra_charge : 0)
            );
            $('#delivery_fee').val(
                Number(response.shipping_price) +
                Number(response.extra_charge ? response.extra_charge : 0)
            );

            $('#distance').val(response.distance);
        }).fail(function(xhr, status, error) {
            // reset lat, lng, road, house, delivery_fee, distance, floor
            $('#delivery_fee_span').text(0);
            $('#delivery_fee').val(0);
            $('#distance').val(0);
            $('#floor').val(0);
            $('#latitude').val(0);
            $('#longitude').val(0);
            $('#road').val('');
            $('#house').val('');
            console.error(xhr.responseText);
        });
        // }
    }

    function getDeliveryFeeByGoogleSearch() {
        const data = {
            store: $('#store').html(),
            type: 'google_search',
            lat: $('#latitude').val(),
            lng: $('#longitude').val(),
            order_by: $('#order_by').val()
        };
        console.log(data);
        $.get({
            url: "{{ route('admin.pos.extra_charge') }}",
            data: data,
        }).done(function(response) {
            // Handle the response
            $('#delivery_fee_span').text(
                Number(response.shipping_price) +
                Number(response.extra_charge ? response.extra_charge : 0)
            );
            $('#delivery_fee').val(
                Number(response.shipping_price) +
                Number(response.extra_charge ? response.extra_charge : 0)
            );
            $('#distance').val(response.distance);
        }).fail(function(xhr, status, error) {
            // Handle errors
            console.error(xhr.responseText);
        });
    }

    function sendDataToMap() {
        let block_id = $('#block_id option:selected').text().trim();
        let road = $('#road').val().trim();
        let house = $('#house').val().trim();
        let floor = $('#floor').val().trim();
        // if (block_id && road && house) {
        // Concatencate the values with spaces
        let address = floor + ' ' + house + ' ' + road + ' ' + block_id;
        console.log(address);
        // Send Address To Herewego Map
        const apiKey = 'rKdZVKfHAvnCHfGP5BoLt0SRhHxT-NMNoSdpXlHHpPk';
        // Make an API call to fetch latitude and longitude
        const apiUrl =
            `https://autosuggest.search.hereapi.com/v1/autosuggest?apiKey=${apiKey}&q=${address}&at=26.13868,50.56178`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {

                // Extract latitude and longitude from the response
                const lat = data.items[0].position.lat;
                const lng = data.items[0].position.lng;

                // Update the latitude and longitude input fields
                $('#latitude').val(lat);
                $('#longitude').val(lng);

                getDeliveryFee(lat, lng);


            })
            .catch(error => console.error('Error fetching data:', error));
        // }
    }

    $('#mapDiv').hide();

    function showMap(checkedStatus) {
        console.log(checkedStatus);
        let mapDiv = $('#mapDiv');
        let mapInput = $('#searchInput');
        let blocksDiv = $('#blocksDiv');
        let block_id = $('#block_id');
        let roadDiv = $('#roadDiv');
        let houseDiv = $('#houseDiv');
        let floorDiv = $('#floorDiv');
        let road = $('#road');
        let house = $('#house');
        let floor = $('#floor');
        let lat = $('#latitude');
        let lng = $('#longitude');

        // empty values
        mapInput.val('');
        block_id.val('');
        road.val('');
        house.val('');
        floor.val('');
        lat.val('');
        lng.val('');
        $('#delivery_fee').val('');
        $('#distance').val('');
        $('#delivery_fee_span').html('0');

        if (checkedStatus) {
            mapDiv.show();
            blocksDiv.hide();
            roadDiv.hide();
            houseDiv.hide();
            floorDiv.hide();
        } else {
            mapDiv.hide();
            blocksDiv.show();
            roadDiv.show();
            houseDiv.show();
            floorDiv.show();
        }

    }
</script>
