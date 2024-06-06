@extends('layouts.admin.app')

@section('title', translate('Zone Wise Module Setup'))

@push('css_or_js')
@endpush

@section('content')
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--26" alt="">
            </span>
            <span>
                {{ translate('cities') }}
            </span>
        </h1>
    </div>
    <!-- End Page Header -->

    <div class="card mt-3">
        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                <h5 class="card-title">{{ translate('messages.cities_list') }}<span class="badge badge-soft-dark ml-2"
                        id="itemCount">{{ $cities->total() }}</span></h5>
                <form class="search-form">
                    <!-- Search -->
                    <div class="input-group input--group">
                        <input type="search" name="search" value="{{ request()?->search ?? null }}"
                            class="form-control min-height-45" placeholder="{{ translate('messages.search_cities') }}"
                            aria-label="{{ translate('messages.ex_:_cities') }}">
                        <button type="submit" class="btn btn--secondary min-height-45"><i class="tio-search"></i></button>
                    </div>
                    <!-- End Search -->
                </form>
            </div>
        </div>
        <form class="p-2 border-0" action="{{ route('admin.business-settings.city_wise.store') }}" method="POST"
            style="width: fit-content">
            @csrf
            <!-- Search -->
            <div class="input-group input--group">
                <input type="text" name="name" class="form-control min-height-45"
                    placeholder="{{ translate('messages.add_cities') }}"
                    aria-label="{{ translate('messages.ex_:_cities') }}">
                <button type="submit" class="btn btn--primary min-height-45">{{ translate('messages.add') }}</button>
            </div>
            <!-- End Search -->
        </form>
        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable" class="table table-borderless table-thead-bordered table-align-middle"
                    data-hs-datatables-options='{
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false,
                        }'>
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('sl') }}</th>
                            <th class="border-0 w--1">{{ translate('messages.name') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>

                    <tbody id="table-div">
                        @foreach ($cities as $key => $city)
                            <tr>
                                <td>{{ $key + $cities->firstItem() }}</td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{ Str::limit($city['name'], 20, '...') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary"href="javascript:"
                                            data-toggle="modal" data-target="#edit-city-{{ $city['id'] }}"
                                            title="{{ translate('messages.edit_city') }}"><i class="tio-edit"></i>
                                        </a>
                                        {{-- Edit Modal --}}
                                        <div class="modal fade" id="edit-city-{{ $city['id'] }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ translate('add_new_city') }}</h5>
                                                        <button type="button" class="close" data-dismiss="modal"
                                                            aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form
                                                            action="{{ route('admin.business-settings.city_wise.update', $city->id) }}"
                                                            method="post" id="product_form">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="row">
                                                                <div class="col-12 col-lg-6">
                                                                    <div class="form-group">
                                                                        <label for="name"
                                                                            class="input-label">{{ translate('name') }}<span
                                                                                class="input-label-secondary text-danger">*</span></label>
                                                                        <input id="name" type="number" name="name"
                                                                            class="form-control"
                                                                            value="{{ $city->name }}"
                                                                            placeholder="{{ translate('messages.name') }}"
                                                                            required>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="btn--container justify-content-end">
                                                                <button type="reset"
                                                                    class="btn btn--reset">{{ translate('reset') }}</button>
                                                                <button type="submit" id="submit_new_city"
                                                                    class="btn btn--primary">{{ translate('submit') }}</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                            href="javascript:" data-id="city-{{ $city['id'] }}"
                                            data-message="{{ translate('Want to delete this city') }}"
                                            title="{{ translate('messages.delete_city') }}"><i
                                                class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{ route('admin.business-settings.city_wise.destroy', $city->id) }}"
                                            method="post" id="city-{{ $city['id'] }}">
                                            @csrf
                                            @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if (count($cities) !== 0)
            <hr>
        @endif
        <div class="page-area">
            {!! $cities->appends($_GET)->links() !!}
        </div>
        @if (count($cities) === 0)
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
