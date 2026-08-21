<div class="container-fluid">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading">
                <strong><i class="fa fa-cluster fa-fw fa-lg" aria-hidden="true"></i> Check Point Cluster IPs</strong>
                @if ($device_count)
                    <span class="text-muted">({{ $device_count }} Check Point {{ Str::plural('device', $device_count) }})</span>
                @endif
            </div>
            <div class="panel-body">
                @if (! empty($errors))
                    @foreach ($errors as $hostname => $msg)
                        <div class="alert alert-warning">Unable to read the Cluster IP table for <strong>{{ $hostname }}</strong>: {{ $msg }}</div>
                    @endforeach
                @endif

                @if (empty($rows))
                    @if ($device_count === 0)
                        <div class="alert alert-info">No Check Point devices selected. Enable devices via <strong>Overview &gt; Plugins &gt; Plugin Admin &gt; CheckpointHA Settings</strong> to list their cluster IPs here.</div>
                    @else
                        <div class="alert alert-info">No Check Point devices with cluster IPs found.</div>
                    @endif
                @else
                    <table id="cp-cluster-ips-table" class="table table-condensed table-hover table-striped table-bordered">
                        <thead>
                        <tr>
                            <th>#</th>
                            @foreach ($columns as $key => $label)
                                <th>
                                    {{ $label }}
                                    <div class="btn-group cp-header-search">
                                        <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" title="Search {{ $label }}">
                                            <i class="fa fa-search" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right cp-search-menu">
                                            <li>
                                                <input type="text" class="form-control cp-col-search" data-column="{{ $loop->index + 1 }}" placeholder="Search {{ $label }}..." autocomplete="off">
                                            </li>
                                        </ul>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                @foreach ($columns as $key => $label)
                                    <td>{{ $row[$key] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

@if (! empty($rows))
    @php
        // LibreNMS has no plugin static-file route, so the vendored
        // DataTables assets are read and inlined server-side.
        $cp_css = base_path('app/Plugins/CheckpointHA/css/');
        $cp_js = base_path('app/Plugins/CheckpointHA/js/');
        $inline_css = fn (string $file): string => str_replace('</style>', '<\/style>', (string) file_get_contents($cp_css . $file));
        $inline_js = fn (string $file): string => str_replace('</script>', '<\/script>', (string) file_get_contents($cp_js . $file));
    @endphp
    <style>{!! $inline_css('dataTables.bootstrap.min.css') !!}</style>
    <style>{!! $inline_css('buttons.bootstrap.min.css') !!}</style>
    <style>
        .cp-search-menu { min-width: 200px; padding: 6px; }
        .cp-search-menu li { padding: 0; }
        .cp-search-menu .form-control { width: 100%; }
        .cp-header-search { float: right; margin-left: 6px; }
        .btn-group .btn.cp-search-active { color: #fff; background-color: #337ab7; border-color: #2e6da4; }
    </style>
    <script>{!! $inline_js('jquery.dataTables.min.js') !!}</script>
    <script>{!! $inline_js('dataTables.bootstrap.min.js') !!}</script>
    <script>{!! $inline_js('dataTables.buttons.min.js') !!}</script>
    <script>{!! $inline_js('buttons.bootstrap.min.js') !!}</script>
    <script>{!! $inline_js('buttons.html5.min.js') !!}</script>
    <script>{!! $inline_js('buttons.print.min.js') !!}</script>
    <script>
        (function () {
            var table = document.getElementById('cp-cluster-ips-table');
            if (! table || ! window.jQuery || ! window.jQuery.fn.dataTable) {
                return;
            }

            window.jQuery(table).DataTable({
                order: [],
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                dom: 'flBrtip',
                buttons: [
                    { extend: 'csvHtml5', text: 'Export CSV', title: 'checkpoint-cluster-ips' },
                    { extend: 'print', text: 'Print' },
                    {
                        text: 'Clear Filters',
                        action: function (e, dt, node, config) {
                            dt.search('').columns().search('').draw();
                            window.jQuery(table).find('input[data-column]').val('').closest('.btn-group').find('.dropdown-toggle').removeClass('cp-search-active');
                            window.jQuery('.dataTables_filter input', window.jQuery(table).closest('.dataTables_wrapper')).val('');
                        }
                    }
                ],
                initComplete: function () {
                    var api = this.api();
                    api.columns().every(function () {
                        var column = this;
                        var input = window.jQuery('input[data-column="' + column.index() + '"]', window.jQuery(table));
                        if (! input.length) {
                            return;
                        }

                        input.on('keyup change clear', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                            input.closest('.btn-group').find('.dropdown-toggle').toggleClass('cp-search-active', this.value !== '');
                        });
                    });

                    window.jQuery(table).find('.cp-header-search .dropdown-toggle').on('click', function (e) {
                        e.stopPropagation();
                        var $btn = window.jQuery(this);
                        if (window.jQuery.fn.dropdown) {
                            $btn.dropdown('toggle');
                        } else {
                            $btn.closest('.btn-group').toggleClass('open');
                        }
                    });

                    window.jQuery(document).on('click', function (e) {
                        if (! window.jQuery(e.target).closest('.cp-header-search').length) {
                            window.jQuery(table).find('.cp-header-search .btn-group.open').removeClass('open');
                        }
                    });
                }
            });
        })();
    </script>
@endif
