<div class="container-fluid">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading">
                <strong><i class="fa fa-cluster fa-fw fa-lg" aria-hidden="true"></i> Check Point Cluster IPs Settings</strong>
                @if ($devices->isNotEmpty())
                    <span class="text-muted">({{ $devices->count() }} Check Point {{ Str::plural('device', $devices->count()) }})</span>
                @endif
            </div>
            <div class="panel-body">
                <p class="text-muted">Only the checked devices are listed on the <strong>Check Point Cluster IPs</strong> page.</p>

                <form method="post" action="{{ route('plugin.update', ['plugin' => $plugin_name]) }}">
                    @csrf

                    @if ($devices->isEmpty())
                        <div class="alert alert-info">No Check Point devices found.</div>
                    @else
                        <table class="table table-condensed table-hover table-striped">
                            <thead>
                            <tr>
                                <th style="width: 30px"><input type="checkbox" id="cp-select-all" aria-label="Select all"></th>
                                <th>Device</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($devices as $device)
                                <tr>
                                    <td><input type="checkbox" name="settings[devices][]" value="{{ $device->device_id }}" @checked(in_array((int) $device->device_id, $selected, true))></td>
                                    <td>{{ $device->sysName ?: $device->hostname }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var selectAll = document.getElementById('cp-select-all');
        if (! selectAll) {
            return;
        }

        selectAll.addEventListener('change', function () {
            var checked = selectAll.checked;
            var boxes = document.querySelectorAll('input[name="settings[devices][]"]');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = checked;
            }
        });
    })();
</script>
