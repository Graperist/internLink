{{-- One coordinator row (OJT Coordinators, grouped by academic year). --}}
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="il-row-avatar">{{ \App\Models\User::initialsOf($coordinator->name) }}</span>
                        <span>
                            <span class="fw-semibold d-block">{{ $coordinator->name }}</span>
                            <span class="text-secondary" style="font-size:.78rem;">{{ $coordinator->id_number }}</span>
                        </span>
                    </div>
                </td>
                <td><span class="badge rounded-pill text-bg-light border fw-bold">{{ $coordinator->campus?->code ?? '—' }}</span> <span class="small">{{ $coordinator->campus?->name }}</span></td>
                <td class="small">{{ $coordinator->email }}</td>
                <td class="small">{{ $coordinator->instructor?->program?->code ?? '—' }}</td>
                <td><span class="badge rounded-pill text-bg-primary">Active</span></td>
                <td class="text-end text-nowrap">
                    <form method="POST" action="{{ route('admin.coordinators.reset-password', $coordinator) }}" class="d-inline"
                          onsubmit="return confirm('Give {{ addslashes($coordinator->name) }} a new temporary password? It will be emailed to {{ addslashes($coordinator->email) }}.')">
                        @csrf
                        <button type="submit" class="il-icon-btn" title="Reset password"><i class="ti ti-key"></i><span class="visually-hidden">Reset password</span></button>
                    </form>
                    <form method="POST" action="{{ route('admin.coordinators.archive', $coordinator) }}" class="d-inline"
                          onsubmit="return confirm('Archive {{ addslashes($coordinator->name) }}? Nothing is deleted: their records are kept, but they can no longer sign in. You can restore them any time.')">
                        @csrf
                        <button type="submit" class="il-icon-btn" title="Archive"><i class="ti ti-archive"></i><span class="visually-hidden">Archive</span></button>
                    </form>
                </td>
            </tr>
