{{-- One coordinator row (OJT Coordinators, grouped by academic year). --}}
            @php $active = $coordinator->status === 'active'; @endphp
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
                <td><span class="badge rounded-pill {{ $active ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $active ? 'Active' : 'Deactivated' }}</span></td>
                <td class="text-end text-nowrap">
                    <form method="POST" action="{{ route('admin.coordinators.reset-password', $coordinator) }}" class="d-inline"
                          onsubmit="return confirm('Give {{ addslashes($coordinator->name) }} a new temporary password? It will be emailed to {{ addslashes($coordinator->email) }}.')">
                        @csrf
                        <button type="submit" class="il-icon-btn" title="Reset password"><i class="ti ti-key"></i><span class="visually-hidden">Reset password</span></button>
                    </form>
                    <form method="POST" action="{{ route('admin.coordinators.status', $coordinator) }}" class="d-inline"
                          onsubmit="return confirm('{{ $active ? 'Deactivate '.addslashes($coordinator->name).'? They will no longer be able to sign in.' : 'Reactivate '.addslashes($coordinator->name).'?' }}')">
                        @csrf
                        <button type="submit" class="il-icon-btn" title="{{ $active ? 'Deactivate' : 'Reactivate' }}">
                            <i class="ti {{ $active ? 'ti-user-off' : 'ti-user-check' }}"></i><span class="visually-hidden">{{ $active ? 'Deactivate' : 'Reactivate' }}</span>
                        </button>
                    </form>
                    @include('partials.admin-delete-button', [
                        'action' => route('admin.coordinators.destroy', $coordinator),
                        'message' => "Delete the OJT Coordinator account of {$coordinator->name} ({$coordinator->id_number})? They will no longer be able to sign in. The campus and its records are kept. This cannot be undone.",
                    ])
                </td>
            </tr>
