<form method="get" action="{{ route($filterRoute) }}" class="filters order-filters" role="search" aria-label="Filtrar solicitudes">
<label class="filter-search">Buscar<input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Asunto, cliente, referencia o identificador"></label>
<label>Estado<select name="status"><option value="">Todos</option>@foreach($statuses as $state)<option value="{{ $state->value }}" @selected(request('status')===$state->value)>{{ $state->label() }}</option>@endforeach</select></label>
<label>Facturación<select name="billing"><option value="">Todas</option>@foreach(['unquoted'=>'Por cotizar','pending'=>'Pago pendiente','paid'=>'Pagada','credit'=>'Identidad API a crédito'] as $value=>$label)<option value="{{ $value }}" @selected(request('billing')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Recibida desde<input type="date" name="from" value="{{ request('from') }}"></label>
<label>Hasta<input type="date" name="to" value="{{ request('to') }}" @if(request('from')) min="{{ request('from') }}" @endif></label>
@if(auth()->user()->isStaff())<label class="check"><input type="checkbox" name="mine" value="1" @checked(request('mine'))>Mis asignadas</label>@endif
<button>Aplicar filtros</button>@if(request()->filled('q') || request()->filled('status') || request()->filled('billing') || request()->filled('from') || request()->filled('to') || request()->filled('mine'))<a href="{{ route($filterRoute) }}">Limpiar</a>@endif
</form>
