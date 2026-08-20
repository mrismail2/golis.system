/* ============================================================
   SmileCare — application logic
   Hash router + role-aware navigation + view rendering.
   ============================================================ */

/* ---------- state ---------- */
const state = {
	role: localStorage.getItem('sc_role') || 'doctor',
	user: localStorage.getItem('sc_user') || 'Dr. Ismail C. Ahmed',
	patients: PATIENTS.slice(),
	appointments: APPOINTMENTS.slice(),
	treatments: TREATMENTS.slice(),
	invoices: INVOICES.slice(),
	inventory: INVENTORY.slice(),
	teeth: JSON.parse(JSON.stringify(TOOTH_STATES)),
	filters: { apptStatus: 'all', apptDay: TODAY, patientQuery: '', invoiceStatus: 'all' }
};

/* ---------- tiny helpers ---------- */
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const view = $('#view');

const money = n => CLINIC.currency + Number(n || 0).toLocaleString();
const initials = name => name
	.split(' ')
	.filter(w => w && !/^(dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?)$/i.test(w))
	.slice(0, 2)
	.map(w => w[0])
	.join('')
	.toUpperCase();
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

const patientById = id => state.patients.find(p => p.id === id) || { name: 'Unknown', id: id };
const serviceById = id => SERVICES.find(s => s.id === id) || { name: '—', price: 0, duration: 0 };

const APPT_BADGE = {
	'Completed': 'success', 'In chair': 'info', 'Waiting': 'warning',
	'Confirmed': 'brand', 'Cancelled': 'danger', 'No show': 'muted'
};
const PAY_BADGE = { 'Paid': 'success', 'Partial': 'warning', 'Unpaid': 'danger' };
const TREAT_BADGE = { 'Completed': 'success', 'In progress': 'info', 'Planned': 'warning' };

const badge = (text, tone) => `<span class="badge badge--${tone || 'muted'}">${esc(text)}</span>`;

function prettyDate(iso) {
	if (!iso) return '—';
	const d = new Date(iso + 'T00:00:00');
	if (isNaN(d)) return iso;
	const days = ['Axd', 'Isn', 'Tal', 'Arb', 'Khm', 'Jim', 'Sab'];
	const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	return `${days[d.getDay()]}, ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

/* ---------- revenue helpers ---------- */
const collectedOn = date => state.invoices.filter(i => i.date === date).reduce((sum, i) => sum + i.paid, 0);

/* Today's bar is recomputed from real invoices so the chart reacts
   as soon as reception takes a payment. */
function weekSeries() {
	return REVENUE_WEEK.map(r => r.day === 'Khm' ? { day: r.day, value: collectedOn(TODAY), today: true } : r);
}

/* ---------- toast ---------- */
function toast(message, type = 'success') {
	const icons = { success: 'bx-check-circle', info: 'bx-info-circle', warn: 'bx-error', error: 'bx-x-circle' };
	const node = document.createElement('div');
	node.className = `toast toast--${type}`;
	node.innerHTML = `<i class='bx ${icons[type]}'></i><span>${esc(message)}</span>`;
	$('#toasts').appendChild(node);
	setTimeout(() => { node.style.opacity = '0'; setTimeout(() => node.remove(), 250); }, 3200);
}

/* ---------- modal ---------- */
function openModal(html, { wide = false } = {}) {
	const modal = $('#modal');
	modal.className = 'modal' + (wide ? ' modal--wide' : '');
	modal.innerHTML = html;
	$('#overlay').classList.add('open');
	$$('[data-close]', modal).forEach(b => b.addEventListener('click', closeModal));
	const firstInput = $('input, select, textarea', modal);
	if (firstInput) setTimeout(() => firstInput.focus(), 60);
	return modal;
}
function closeModal() { $('#overlay').classList.remove('open'); }

$('#overlay').addEventListener('click', e => { if (e.target.id === 'overlay') closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ============================================================
   NAVIGATION (role aware)
   ============================================================ */
const NAV_MAIN = [
	{ route: 'dashboard',    icon: 'bxs-dashboard',        label: 'Dashboard',   roles: ['doctor', 'staff'] },
	{ route: 'appointments', icon: 'bx-calendar-check',    label: 'Ballamaha',   roles: ['doctor', 'staff'], badge: () => state.appointments.filter(a => a.date === TODAY && ['Waiting', 'Confirmed'].includes(a.status)).length },
	{ route: 'patients',     icon: 'bxs-user-detail',      label: 'Bukaanka',    roles: ['doctor', 'staff'] },
	{ route: 'treatments',   icon: 'bx-plus-medical',      label: 'Daaweynta',   roles: ['doctor', 'staff'] },
	{ route: 'billing',      icon: 'bx-receipt',           label: 'Lacagta',     roles: ['doctor', 'staff'] }
];
const NAV_ADMIN = [
	{ route: 'inventory', icon: 'bx-box',        label: 'Bakhaarka',  roles: ['doctor', 'staff'], badge: () => state.inventory.filter(i => i.qty < i.min).length },
	{ route: 'staff',     icon: 'bxs-group',     label: 'Shaqaalaha', roles: ['doctor'] },
	{ route: 'reports',   icon: 'bx-line-chart', label: 'Warbixin',   roles: ['doctor'] },
	{ route: 'settings',  icon: 'bxs-cog',       label: 'Settings',   roles: ['doctor', 'staff'] }
];

function allowed(item) { return item.roles.includes(state.role); }

function renderNav() {
	const build = items => items.filter(allowed).map(item => {
		const count = typeof item.badge === 'function' ? item.badge() : 0;
		return `
			<li data-route="${item.route}">
				<a href="#/${item.route}">
					<i class='bx ${item.icon}'></i>
					<span class="text">${item.label}</span>
					${count ? `<span class="pill">${count}</span>` : ''}
				</a>
			</li>`;
	}).join('');

	$('#menuMain').innerHTML = build(NAV_MAIN);
	$('#menuAdmin').innerHTML = build(NAV_ADMIN) + `
		<li>
			<a href="index.html" id="logoutLink">
				<i class='bx bxs-log-out-circle'></i>
				<span class="text">Ka bax</span>
			</a>
		</li>`;
}

function markActive(route) {
	$$('#sidebar .side-menu li').forEach(li => li.classList.toggle('active', li.dataset.route === route));
}

/* ---------- identity in chrome ---------- */
function renderIdentity() {
	const me = STAFF.find(s => s.name === state.user) || STAFF[0];
	$('#sideName').textContent = me.name;
	$('#topName').textContent = me.name;
	$('#sideRole').textContent = me.title;
	$('#topRole').textContent = me.title;
	const img = state.role === 'doctor' ? 'ismail.image.jpg' : 'profile.jpg';
	$('#sideAvatar').src = img;
	$('#topAvatar').src = img;
}

/* ============================================================
   ROUTER
   ============================================================ */
const ROUTES = {
	dashboard:    viewDashboard,
	appointments: viewAppointments,
	patients:     viewPatients,
	patient:      viewPatientDetail,
	treatments:   viewTreatments,
	billing:      viewBilling,
	inventory:    viewInventory,
	staff:        viewStaff,
	reports:      viewReports,
	settings:     viewSettings
};

const ROUTE_ROLES = {};
NAV_MAIN.concat(NAV_ADMIN).forEach(i => { ROUTE_ROLES[i.route] = i.roles; });
ROUTE_ROLES.patient = ['doctor', 'staff'];

function router() {
	const raw = (location.hash || '#/dashboard').replace(/^#\//, '');
	const [route, param] = raw.split('/');
	const fn = ROUTES[route];

	if (!fn) return renderNotFound();
	if (ROUTE_ROLES[route] && !ROUTE_ROLES[route].includes(state.role)) return renderDenied();

	view.innerHTML = '';
	const wrap = document.createElement('div');
	wrap.className = 'view';
	wrap.innerHTML = fn(param);
	view.appendChild(wrap);

	markActive(route === 'patient' ? 'patients' : route);
	document.body.classList.remove('nav-open');
	window.scrollTo({ top: 0 });
	bindViewEvents(route, param);
}

function renderNotFound() {
	view.innerHTML = `<div class="card"><div class="empty">
		<i class='bx bx-compass'></i><b>Boggan lama helin</b>
		<p>Isku day inaad ku noqotid dashboard-ka.</p>
		<a class="btn btn--primary btn--sm" href="#/dashboard" style="margin-top:14px">Dashboard</a>
	</div></div>`;
}
function renderDenied() {
	view.innerHTML = `<div class="card"><div class="empty">
		<i class='bx bx-lock-alt'></i><b>Ogolaansho ma lihid</b>
		<p>Qaybtan waxa gali kara dhakhtarka kaliya.</p>
		<a class="btn btn--primary btn--sm" href="#/dashboard" style="margin-top:14px">Ku noqo dashboard-ka</a>
	</div></div>`;
}

/* ---------- page header helper ---------- */
function pageHead(title, crumb, actions = '') {
	return `
	<div class="page-head">
		<div>
			<h1>${esc(title)}</h1>
			<div class="crumbs">
				<a href="#/dashboard">Home</a>
				<i class='bx bx-chevron-right'></i>
				<span class="active">${esc(crumb)}</span>
			</div>
		</div>
		<div class="actions">${actions}</div>
	</div>`;
}

/* ============================================================
   VIEW — DASHBOARD
   ============================================================ */
function viewDashboard() {
	const today = state.appointments.filter(a => a.date === TODAY);
	const done = today.filter(a => a.status === 'Completed').length;
	const waiting = today.filter(a => ['Waiting', 'In chair'].includes(a.status));
	const revenueToday = collectedOn(TODAY);
	const paidToday = state.invoices.filter(i => i.date === TODAY && i.paid > 0).length;
	const outstanding = state.invoices.reduce((s, i) => s + (i.total - i.paid), 0);
	const lowStock = state.inventory.filter(i => i.qty < i.min);

	const week = weekSeries();
	const maxRev = Math.max(...week.map(r => r.value), 1);
	const bars = week.map(r => `
		<div class="bars__col ${r.value === 0 ? 'muted' : ''}">
			<div class="bars__bar" style="height:${Math.max((r.value / maxRev) * 100, 3)}%" data-value="${money(r.value)}"></div>
			<small${r.today ? ' style="color:var(--brand-dark);font-weight:600"' : ''}>${r.day}</small>
		</div>`).join('');

	/* service mix donut */
	const mix = [
		{ label: 'Nadaafad & Baaritaan', value: 38, color: '#0EA5A4' },
		{ label: 'Buuxin & Restorative', value: 27, color: '#6366F1' },
		{ label: 'Endodontic (RCT)',     value: 20, color: '#D97706' },
		{ label: 'Qalliin & Kale',       value: 15, color: '#94A3B8' }
	];
	let acc = 0;
	const slices = mix.map(m => { const from = acc; acc += m.value; return `${m.color} ${from}% ${acc}%`; }).join(', ');

	const queueHtml = today
		.filter(a => a.status !== 'Cancelled')
		.sort((a, b) => a.time.localeCompare(b.time))
		.map(a => {
			const p = patientById(a.patientId);
			const s = serviceById(a.serviceId);
			return `
			<div class="queue__item" data-goto-patient="${p.id}">
				<div class="queue__time">${a.time}<small>${s.duration}m</small></div>
				<div class="queue__body">
					<b>${esc(p.name)}</b>
					<span>${esc(s.name)} · ${esc(a.room)} · ${esc(a.doctor.replace('Dr. ', 'Dr '))}</span>
				</div>
				${badge(a.status, APPT_BADGE[a.status])}
			</div>`;
		}).join('') || `<div class="empty"><i class='bx bx-calendar-x'></i><b>Ballan maanta ma jiro</b></div>`;

	const activity = ACTIVITY.map(a => `
		<div class="timeline__item">
			<h4>${esc(a.title)}</h4>
			<time>${esc(a.time)} · ${esc(a.by)}</time>
			<p>${esc(a.text)}</p>
		</div>`).join('');

	return `
	${pageHead('Dashboard', 'Guudmar maalinle', `
		<button class="btn btn--ghost" data-action="new-patient"><i class='bx bx-user-plus'></i> Bukaan cusub</button>
		<button class="btn btn--primary" data-action="new-appointment"><i class='bx bx-calendar-plus'></i> Ballan cusub</button>
	`)}

	<div class="grid grid--kpi" style="margin-bottom:18px">
		<div class="card kpi">
			<div class="kpi__icon kpi__icon--brand"><i class='bx bx-calendar-check'></i></div>
			<div>
				<h3>${today.length}</h3>
				<p>Ballamaha maanta</p>
				<span class="trend trend--up"><i class='bx bx-up-arrow-alt'></i> ${done} la dhammeeyay</span>
			</div>
		</div>
		<div class="card kpi">
			<div class="kpi__icon kpi__icon--warn"><i class='bx bx-time-five'></i></div>
			<div>
				<h3>${waiting.length}</h3>
				<p>Sugaya / kursiga</p>
				<span class="trend"><i class='bx bx-user'></i> ${waiting.length ? esc(patientById(waiting[0].patientId).name.split(' ')[0]) + ' ku xiga' : 'Safku waa faaruq'}</span>
			</div>
		</div>
		<div class="card kpi">
			<div class="kpi__icon kpi__icon--ok"><i class='bx bx-dollar-circle'></i></div>
			<div>
				<h3>${money(revenueToday)}</h3>
				<p>Dakhliga maanta</p>
				<span class="trend"><i class='bx bx-receipt'></i> ${paidToday} fatuurad la bixiyay</span>
			</div>
		</div>
		<div class="card kpi">
			<div class="kpi__icon kpi__icon--info"><i class='bx bx-group'></i></div>
			<div>
				<h3>${state.patients.length}</h3>
				<p>Bukaanka diiwaangashan</p>
				<span class="trend trend--up"><i class='bx bx-plus'></i> 2 usbuucan</span>
			</div>
		</div>
	</div>

	<div class="grid grid--2" style="margin-bottom:18px">
		<div class="card">
			<div class="card__head">
				<div><h3>Safka maanta</h3><p>${prettyDate(TODAY)}</p></div>
				<div class="right"><a class="btn btn--ghost btn--sm" href="#/appointments">Dhammaan <i class='bx bx-right-arrow-alt'></i></a></div>
			</div>
			<div class="card__body card__body--flush"><div class="queue">${queueHtml}</div></div>
		</div>

		<div class="grid">
			<div class="card">
				<div class="card__head"><div><h3>Dakhliga usbuuca</h3><p>Toddobaadkan</p></div></div>
				<div class="card__body"><div class="bars">${bars}</div></div>
			</div>
			<div class="card">
				<div class="card__head"><div><h3>Noocyada adeegga</h3></div></div>
				<div class="card__body">
					<div class="donut">
						<div class="donut__ring" style="background:conic-gradient(${slices})">
							<div class="donut__center"><b>${today.length}</b><small>maanta</small></div>
						</div>
						<ul class="legend">
							${mix.map(m => `<li><span class="key" style="background:${m.color}"></span>${m.label}<b>${m.value}%</b></li>`).join('')}
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="grid grid--2">
		<div class="card">
			<div class="card__head">
				<div><h3>Lacagta la sugayo</h3><p>Faturado aan si buuxda loo bixin</p></div>
				<div class="right">${badge(money(outstanding) + ' furan', 'warning')}</div>
			</div>
			<div class="card__body card__body--flush">
				<div class="table-wrap">
					<table>
						<thead><tr><th>Fatuurad</th><th>Bukaan</th><th>Wadar</th><th>Hadhaaga</th><th>Xaalad</th></tr></thead>
						<tbody>
							${state.invoices.filter(i => i.status !== 'Paid').map(i => `
								<tr>
									<td><b>${esc(i.id)}</b></td>
									<td>${esc(patientById(i.patientId).name)}</td>
									<td>${money(i.total)}</td>
									<td><b>${money(i.total - i.paid)}</b></td>
									<td>${badge(i.status, PAY_BADGE[i.status])}</td>
								</tr>`).join('')}
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="grid">
			<div class="card">
				<div class="card__head"><div><h3>Dhaqdhaqaaqa</h3><p>Waxa dhacay maanta</p></div></div>
				<div class="card__body"><div class="timeline">${activity}</div></div>
			</div>
			${lowStock.length ? `
			<div class="card">
				<div class="card__head">
					<div><h3>Digniin bakhaar</h3><p>${lowStock.length} shay ayaa yar</p></div>
					<div class="right"><a class="btn btn--ghost btn--sm" href="#/inventory">Fur</a></div>
				</div>
				<div class="card__body">
					${lowStock.map(i => `
						<div style="margin-bottom:14px">
							<div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
								<span>${esc(i.name)}</span><b>${i.qty}/${i.min} ${esc(i.unit)}</b>
							</div>
							<div class="meter"><span style="width:${Math.min((i.qty / i.min) * 100, 100)}%;background:var(--danger)"></span></div>
						</div>`).join('')}
				</div>
			</div>` : ''}
		</div>
	</div>`;
}

/* ============================================================
   VIEW — APPOINTMENTS
   ============================================================ */
function viewAppointments() {
	const days = [...new Set(state.appointments.map(a => a.date))].sort();
	const f = state.filters;

	const rows = state.appointments
		.filter(a => a.date === f.apptDay)
		.filter(a => f.apptStatus === 'all' || a.status === f.apptStatus)
		.sort((a, b) => a.time.localeCompare(b.time))
		.map(a => {
			const p = patientById(a.patientId);
			const s = serviceById(a.serviceId);
			return `
			<tr>
				<td class="nowrap"><b>${a.time}</b><br><span style="font-size:12px;color:var(--text-2)">${s.duration} daq</span></td>
				<td>
					<div class="person">
						<span class="avatar">${initials(p.name)}</span>
						<div><b>${esc(p.name)}</b><span>${esc(p.id)} · ${esc(p.phone)}</span></div>
					</div>
				</td>
				<td>${esc(s.name)}</td>
				<td>${esc(a.doctor)}</td>
				<td>${esc(a.room)}</td>
				<td>${badge(a.status, APPT_BADGE[a.status])}</td>
				<td>
					<div class="row-actions">
						<button title="U wareeji kursiga" data-appt-status="${a.id}"><i class='bx bx-play-circle'></i></button>
						<button title="Fur bukaanka" data-goto-patient="${p.id}"><i class='bx bx-user'></i></button>
						<button class="danger" title="Baaji" data-appt-cancel="${a.id}"><i class='bx bx-x-circle'></i></button>
					</div>
				</td>
			</tr>`;
		}).join('');

	const statuses = ['all', 'Confirmed', 'Waiting', 'In chair', 'Completed', 'Cancelled'];

	return `
	${pageHead('Ballamaha', 'Jadwalka', `
		<button class="btn btn--ghost" data-action="print"><i class='bx bx-printer'></i> Daabac jadwalka</button>
		<button class="btn btn--primary" data-action="new-appointment"><i class='bx bx-calendar-plus'></i> Ballan cusub</button>
	`)}

	<div class="grid grid--kpi" style="margin-bottom:18px">
		${[
			['Wadarta maanta', state.appointments.filter(a => a.date === TODAY).length, 'bx-calendar', 'brand'],
			['Sugaya', state.appointments.filter(a => a.date === TODAY && a.status === 'Waiting').length, 'bx-time', 'warn'],
			['La dhammeeyay', state.appointments.filter(a => a.date === TODAY && a.status === 'Completed').length, 'bx-check-double', 'ok'],
			['La baajiyay', state.appointments.filter(a => a.date === TODAY && a.status === 'Cancelled').length, 'bx-x', 'info']
		].map(([label, val, icon, tone]) => `
			<div class="card kpi">
				<div class="kpi__icon kpi__icon--${tone}"><i class='bx ${icon}'></i></div>
				<div><h3>${val}</h3><p>${label}</p></div>
			</div>`).join('')}
	</div>

	<div class="card">
		<div class="toolbar">
			<select id="dayFilter">
				${days.map(d => `<option value="${d}" ${d === f.apptDay ? 'selected' : ''}>${prettyDate(d)}${d === TODAY ? ' · Maanta' : ''}</option>`).join('')}
			</select>
			<div class="segmented" id="statusSeg">
				${statuses.map(s => `<button class="${f.apptStatus === s ? 'active' : ''}" data-status="${s}">${s === 'all' ? 'Dhammaan' : s}</button>`).join('')}
			</div>
			<div class="right">
				<span class="badge badge--muted badge--plain"><i class='bx bx-user-voice'></i> 2 dhakhtar oo shaqada jooga</span>
			</div>
		</div>
		<div class="table-wrap">
			<table>
				<thead>
					<tr><th>Waqti</th><th>Bukaan</th><th>Adeeg</th><th>Dhakhtar</th><th>Qol</th><th>Xaalad</th><th style="text-align:right">Ficil</th></tr>
				</thead>
				<tbody>${rows || `<tr><td colspan="7"><div class="empty"><i class='bx bx-calendar-x'></i><b>Ballan lama helin</b><p>Beddel maalinta ama shaandhaynta.</p></div></td></tr>`}</tbody>
			</table>
		</div>
	</div>`;
}

/* ============================================================
   VIEW — PATIENTS
   ============================================================ */
function viewPatients() {
	const q = state.filters.patientQuery.toLowerCase();
	const list = state.patients.filter(p =>
		!q || p.name.toLowerCase().includes(q) || p.id.toLowerCase().includes(q) || p.phone.includes(q)
	);

	const rows = list.map(p => `
		<tr>
			<td>
				<div class="person">
					<span class="avatar">${initials(p.name)}</span>
					<div><b>${esc(p.name)}</b><span>${esc(p.id)}</span></div>
				</div>
			</td>
			<td>${esc(p.gender)} · ${p.age} sano</td>
			<td>${esc(p.phone)}</td>
			<td class="nowrap">${prettyDate(p.lastVisit)}</td>
			<td>${p.allergies && p.allergies !== 'Ma jiro' ? badge(p.allergies, 'danger') : badge('Ma jiro', 'muted')}</td>
			<td>${p.balance > 0 ? `<b style="color:var(--danger)">${money(p.balance)}</b>` : money(0)}</td>
			<td>
				<div class="row-actions">
					<button title="Fur diiwaanka" data-goto-patient="${p.id}"><i class='bx bx-folder-open'></i></button>
					<button title="Ballan qabo" data-book="${p.id}"><i class='bx bx-calendar-plus'></i></button>
					<button title="Wac" data-call="${esc(p.phone)}"><i class='bx bx-phone'></i></button>
				</div>
			</td>
		</tr>`).join('');

	return `
	${pageHead('Bukaanka', 'Diiwaanka bukaanka', `
		<button class="btn btn--ghost" data-action="export"><i class='bx bx-download'></i> Soo dejiso liiska</button>
		<button class="btn btn--primary" data-action="new-patient"><i class='bx bx-user-plus'></i> Bukaan cusub</button>
	`)}

	<div class="card">
		<div class="toolbar">
			<div class="searchbox">
				<i class='bx bx-search'></i>
				<input type="search" id="patientSearch" placeholder="Magac, ID ama telefoon..." value="${esc(state.filters.patientQuery)}">
			</div>
			<div class="right">
				<span class="badge badge--brand badge--plain"><i class='bx bx-group'></i> ${list.length} bukaan</span>
			</div>
		</div>
		<div class="table-wrap">
			<table>
				<thead>
					<tr><th>Bukaan</th><th>Jinsi / Da'</th><th>Telefoon</th><th>Booqasho u dambeysay</th><th>Xasaasiyad</th><th>Deyn</th><th style="text-align:right">Ficil</th></tr>
				</thead>
				<tbody>${rows || `<tr><td colspan="7"><div class="empty"><i class='bx bx-user-x'></i><b>Bukaan lama helin</b><p>Isku day magac kale.</p></div></td></tr>`}</tbody>
			</table>
		</div>
	</div>`;
}

/* ============================================================
   VIEW — PATIENT DETAIL (+ odontogram)
   ============================================================ */
const UPPER_RIGHT = ['18', '17', '16', '15', '14', '13', '12', '11'];
const UPPER_LEFT  = ['21', '22', '23', '24', '25', '26', '27', '28'];
const LOWER_RIGHT = ['48', '47', '46', '45', '44', '43', '42', '41'];
const LOWER_LEFT  = ['31', '32', '33', '34', '35', '36', '37', '38'];

const TOOTH_ICON = {
	healthy: 'bx-check', caries: 'bx-error', filled: 'bx-been-here',
	crown: 'bxs-crown', missing: 'bx-x', treated: 'bx-check-double'
};
const TOOTH_ORDER = ['healthy', 'caries', 'filled', 'crown', 'treated', 'missing'];

function toothButton(patientId, num) {
	const st = (state.teeth[patientId] || {})[num] || 'healthy';
	return `
	<button class="tooth" data-tooth="${num}" data-state="${st}" title="Ilig ${num} — ${st}">
		<span class="tooth__shape"><i class='bx ${TOOTH_ICON[st]}'></i></span>
		<small>${num}</small>
	</button>`;
}

function odontogram(patientId) {
	const arch = (right, left) => `
		<div class="teeth">
			${right.map(n => toothButton(patientId, n)).join('')}
			<span class="gap"></span>
			${left.map(n => toothButton(patientId, n)).join('')}
		</div>`;
	return `
	<div class="odontogram">
		<div class="odontogram__arch"><label>Daanka sare (Maxillary)</label>${arch(UPPER_RIGHT, UPPER_LEFT)}</div>
		<div class="odontogram__arch"><label>Daanka hoose (Mandibular)</label>${arch(LOWER_RIGHT, LOWER_LEFT)}</div>
		<div class="tooth-legend">
			<span><i style="border-color:var(--line)"></i> Caafimaad</span>
			<span><i style="border-color:var(--danger);background:var(--danger-soft)"></i> Caries</span>
			<span><i style="border-color:var(--info);background:var(--info-soft)"></i> Filling</span>
			<span><i style="border-color:var(--warning);background:var(--warning-soft)"></i> Crown</span>
			<span><i style="border-color:var(--success);background:var(--success-soft)"></i> La daaweeyay</span>
			<span><i style="border-style:dashed"></i> Maqan</span>
		</div>
		<p style="font-size:12.5px;color:var(--text-2)"><i class='bx bx-info-circle'></i> Guji ilig si aad u beddesho xaaladdiisa.</p>
	</div>`;
}

function viewPatientDetail(id) {
	const p = state.patients.find(x => x.id === id);
	if (!p) return `<div class="card"><div class="empty"><i class='bx bx-user-x'></i><b>Bukaankan lama helin</b></div></div>`;

	const appts = state.appointments.filter(a => a.patientId === p.id).sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time));
	const treats = state.treatments.filter(t => t.patientId === p.id);
	const invs = state.invoices.filter(i => i.patientId === p.id);
	const spent = invs.reduce((s, i) => s + i.paid, 0);

	return `
	${pageHead(p.name, 'Diiwaanka bukaanka', `
		<a class="btn btn--ghost" href="#/patients"><i class='bx bx-arrow-back'></i> Liiska</a>
		<button class="btn btn--ghost" data-book="${p.id}"><i class='bx bx-calendar-plus'></i> Ballan</button>
		${state.role === 'doctor' ? `<button class="btn btn--primary" data-action="new-treatment" data-patient="${p.id}"><i class='bx bx-plus-medical'></i> Daaweyn ku dar</button>` : ''}
	`)}

	<div class="card" style="margin-bottom:18px">
		<div class="card__body">
			<div class="profile-hero">
				<img src="profile.jpg" alt="">
				<div>
					<h2>${esc(p.name)}</h2>
					<div class="sub">
						<span><i class='bx bx-id-card'></i> ${esc(p.id)}</span>
						<span><i class='bx bx-user'></i> ${esc(p.gender)}, ${p.age} sano</span>
						<span><i class='bx bx-phone'></i> ${esc(p.phone)}</span>
						<span><i class='bx bx-map'></i> ${esc(p.address)}</span>
					</div>
				</div>
				<div class="right">
					${p.balance > 0 ? badge('Deyn ' + money(p.balance), 'danger') : badge('Lacag furan ma leh', 'success')}
				</div>
			</div>

			<div class="facts" style="margin-top:20px">
				<div class="fact"><small>Nooca dhiigga</small><b>${esc(p.blood)}</b></div>
				<div class="fact"><small>Xasaasiyad</small><b style="color:${p.allergies !== 'Ma jiro' ? 'var(--danger)' : 'inherit'}">${esc(p.allergies)}</b></div>
				<div class="fact"><small>Booqasho u dambeysay</small><b>${prettyDate(p.lastVisit)}</b></div>
				<div class="fact"><small>Wadarta booqashooyinka</small><b>${appts.length}</b></div>
				<div class="fact"><small>Wixii uu bixiyay</small><b>${money(spent)}</b></div>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="tabs" id="patientTabs">
			<button class="active" data-tab="chart">Odontogram</button>
			<button data-tab="treatments">Daaweynta (${treats.length})</button>
			<button data-tab="appts">Ballamaha (${appts.length})</button>
			<button data-tab="billing">Faturado (${invs.length})</button>
			<button data-tab="notes">Xusuusqorka</button>
		</div>

		<div class="card__body" id="tabChart">${odontogram(p.id)}</div>

		<div class="card__body card__body--flush hidden" id="tabTreatments">
			<div class="table-wrap"><table>
				<thead><tr><th>ID</th><th>Ilig</th><th>Adeeg</th><th>Taariikh</th><th>Dhakhtar</th><th>Qiimo</th><th>Xaalad</th></tr></thead>
				<tbody>${treats.map(t => `
					<tr>
						<td><b>${esc(t.id)}</b></td>
						<td>${esc(t.tooth)}</td>
						<td>${esc(serviceById(t.serviceId).name)}<br><span style="font-size:12px;color:var(--text-2)">${esc(t.note)}</span></td>
						<td class="nowrap">${prettyDate(t.date)}</td>
						<td>${esc(t.doctor)}</td>
						<td>${money(t.cost)}</td>
						<td>${badge(t.status, TREAT_BADGE[t.status])}</td>
					</tr>`).join('') || `<tr><td colspan="7"><div class="empty"><i class='bx bx-plus-medical'></i><b>Daaweyn lama diiwaangelin</b></div></td></tr>`}
				</tbody>
			</table></div>
		</div>

		<div class="card__body card__body--flush hidden" id="tabAppts">
			<div class="table-wrap"><table>
				<thead><tr><th>Taariikh</th><th>Waqti</th><th>Adeeg</th><th>Dhakhtar</th><th>Xaalad</th></tr></thead>
				<tbody>${appts.map(a => `
					<tr>
						<td class="nowrap">${prettyDate(a.date)}</td>
						<td>${a.time}</td>
						<td>${esc(serviceById(a.serviceId).name)}</td>
						<td>${esc(a.doctor)}</td>
						<td>${badge(a.status, APPT_BADGE[a.status])}</td>
					</tr>`).join('')}
				</tbody>
			</table></div>
		</div>

		<div class="card__body card__body--flush hidden" id="tabBilling">
			<div class="table-wrap"><table>
				<thead><tr><th>Fatuurad</th><th>Taariikh</th><th>Faahfaahin</th><th>Wadar</th><th>La bixiyay</th><th>Xaalad</th></tr></thead>
				<tbody>${invs.map(i => `
					<tr>
						<td><b>${esc(i.id)}</b></td>
						<td class="nowrap">${prettyDate(i.date)}</td>
						<td>${esc(i.items)}</td>
						<td>${money(i.total)}</td>
						<td>${money(i.paid)}</td>
						<td>${badge(i.status, PAY_BADGE[i.status])}</td>
					</tr>`).join('') || `<tr><td colspan="6"><div class="empty"><i class='bx bx-receipt'></i><b>Fatuurad ma jirto</b></div></td></tr>`}
				</tbody>
			</table></div>
		</div>

		<div class="card__body hidden" id="tabNotes">
			<div class="field">
				<label>Xusuusqorka caafimaad</label>
				<textarea id="patientNote" ${state.role === 'staff' ? 'readonly' : ''}>${esc(p.notes)}</textarea>
				<p class="hint">${state.role === 'staff' ? 'Shaqaaluhu waa akhris kaliya — dhakhtarka ayaa wax ka beddeli kara.' : 'Wax ka beddel kadibna kaydi.'}</p>
			</div>
			${state.role === 'doctor' ? `<button class="btn btn--primary" data-save-note="${p.id}"><i class='bx bx-save'></i> Kaydi</button>` : ''}
		</div>
	</div>`;
}

/* ============================================================
   VIEW — TREATMENTS
   ============================================================ */
function viewTreatments() {
	const rows = state.treatments
		.slice()
		.sort((a, b) => b.date.localeCompare(a.date))
		.map(t => {
			const p = patientById(t.patientId);
			return `
			<tr>
				<td><b>${esc(t.id)}</b></td>
				<td>
					<div class="person">
						<span class="avatar">${initials(p.name)}</span>
						<div><b>${esc(p.name)}</b><span>${esc(p.id)}</span></div>
					</div>
				</td>
				<td>${esc(serviceById(t.serviceId).name)}</td>
				<td>${t.tooth === '—' ? '—' : `<span class="badge badge--muted badge--plain">Ilig ${esc(t.tooth)}</span>`}</td>
				<td class="nowrap">${prettyDate(t.date)}</td>
				<td>${esc(t.doctor)}</td>
				<td>${money(t.cost)}</td>
				<td>${badge(t.status, TREAT_BADGE[t.status])}</td>
				<td><div class="row-actions"><button data-goto-patient="${p.id}" title="Fur diiwaanka"><i class='bx bx-folder-open'></i></button></div></td>
			</tr>`;
		}).join('');

	return `
	${pageHead('Daaweynta', 'Qorshaha & taariikhda', state.role === 'doctor'
		? `<button class="btn btn--primary" data-action="new-treatment"><i class='bx bx-plus-medical'></i> Daaweyn cusub</button>` : '')}

	<div class="grid grid--3" style="margin-bottom:18px">
		${[
			['La qorsheeyay', state.treatments.filter(t => t.status === 'Planned').length, 'bx-list-check', 'warn'],
			['Socda', state.treatments.filter(t => t.status === 'In progress').length, 'bx-loader-circle', 'info'],
			['La dhammeeyay', state.treatments.filter(t => t.status === 'Completed').length, 'bx-check-double', 'ok']
		].map(([l, v, i, tone]) => `
			<div class="card kpi"><div class="kpi__icon kpi__icon--${tone}"><i class='bx ${i}'></i></div><div><h3>${v}</h3><p>${l}</p></div></div>`).join('')}
	</div>

	<div class="grid grid--2">
		<div class="card">
			<div class="card__head"><div><h3>Diiwaanka daaweynta</h3><p>Dhammaan bukaanka</p></div></div>
			<div class="table-wrap"><table>
				<thead><tr><th>ID</th><th>Bukaan</th><th>Adeeg</th><th>Ilig</th><th>Taariikh</th><th>Dhakhtar</th><th>Qiimo</th><th>Xaalad</th><th></th></tr></thead>
				<tbody>${rows}</tbody>
			</table></div>
		</div>

		<div class="card">
			<div class="card__head"><div><h3>Qiimaha adeegyada</h3><p>Liiska rasmiga ah ee clinic-ka</p></div></div>
			<div class="table-wrap"><table>
				<thead><tr><th>Adeeg</th><th>Muddo</th><th>Qiime</th></tr></thead>
				<tbody>${SERVICES.map(s => `
					<tr><td>${esc(s.name)}</td><td>${s.duration} daq</td><td><b>${money(s.price)}</b></td></tr>`).join('')}
				</tbody>
			</table></div>
		</div>
	</div>`;
}

/* ============================================================
   VIEW — BILLING
   ============================================================ */
function viewBilling() {
	const f = state.filters.invoiceStatus;
	const list = state.invoices.filter(i => f === 'all' || i.status === f);
	const collected = state.invoices.reduce((s, i) => s + i.paid, 0);
	const outstanding = state.invoices.reduce((s, i) => s + (i.total - i.paid), 0);

	const rows = list.map(i => {
		const p = patientById(i.patientId);
		const due = i.total - i.paid;
		return `
		<tr>
			<td><b>${esc(i.id)}</b></td>
			<td>
				<div class="person">
					<span class="avatar">${initials(p.name)}</span>
					<div><b>${esc(p.name)}</b><span>${esc(p.id)}</span></div>
				</div>
			</td>
			<td>${esc(i.items)}</td>
			<td class="nowrap">${prettyDate(i.date)}</td>
			<td>${money(i.total)}</td>
			<td>${money(i.paid)}</td>
			<td>${due > 0 ? `<b style="color:var(--danger)">${money(due)}</b>` : money(0)}</td>
			<td>${esc(i.method)}</td>
			<td>${badge(i.status, PAY_BADGE[i.status])}</td>
			<td>
				<div class="row-actions">
					${due > 0 ? `<button title="Lacag qaado" data-pay="${i.id}"><i class='bx bx-dollar-circle'></i></button>` : ''}
					<button title="Daabac" data-action="print"><i class='bx bx-printer'></i></button>
				</div>
			</td>
		</tr>`;
	}).join('');

	return `
	${pageHead('Lacagta', 'Faturado & lacag-bixin', `
		<button class="btn btn--ghost" data-action="export"><i class='bx bx-download'></i> Warbixin</button>
		<button class="btn btn--primary" data-action="new-invoice"><i class='bx bx-receipt'></i> Fatuurad cusub</button>
	`)}

	<div class="grid grid--3" style="margin-bottom:18px">
		<div class="card kpi"><div class="kpi__icon kpi__icon--ok"><i class='bx bx-dollar-circle'></i></div><div><h3>${money(collected)}</h3><p>Wadarta la ururiyay</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--warn"><i class='bx bx-hourglass'></i></div><div><h3>${money(outstanding)}</h3><p>Lacag la sugayo</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--info"><i class='bx bx-receipt'></i></div><div><h3>${state.invoices.length}</h3><p>Faturado guud</p></div></div>
	</div>

	<div class="card">
		<div class="toolbar">
			<div class="segmented" id="invoiceSeg">
				${['all', 'Paid', 'Partial', 'Unpaid'].map(s => `<button class="${f === s ? 'active' : ''}" data-invoice-status="${s}">${s === 'all' ? 'Dhammaan' : s}</button>`).join('')}
			</div>
			<div class="right"><span class="badge badge--muted badge--plain"><i class='bx bx-wallet'></i> Cash · EVC Plus · Card</span></div>
		</div>
		<div class="table-wrap"><table>
			<thead><tr><th>Fatuurad</th><th>Bukaan</th><th>Faahfaahin</th><th>Taariikh</th><th>Wadar</th><th>La bixiyay</th><th>Hadhaaga</th><th>Habka</th><th>Xaalad</th><th style="text-align:right">Ficil</th></tr></thead>
			<tbody>${rows || `<tr><td colspan="10"><div class="empty"><i class='bx bx-receipt'></i><b>Fatuurad lama helin</b></div></td></tr>`}</tbody>
		</table></div>
	</div>`;
}

/* ============================================================
   VIEW — INVENTORY
   ============================================================ */
function viewInventory() {
	const rows = state.inventory.map(i => {
		const low = i.qty < i.min;
		const pct = Math.min((i.qty / (i.min * 2)) * 100, 100);
		return `
		<tr>
			<td><b>${esc(i.name)}</b><br><span style="font-size:12px;color:var(--text-2)">${esc(i.id)}</span></td>
			<td>${badge(i.category, 'muted')}</td>
			<td><b>${i.qty}</b> ${esc(i.unit)}</td>
			<td>${i.min} ${esc(i.unit)}</td>
			<td style="min-width:150px">
				<div class="meter"><span style="width:${pct}%;background:${low ? 'var(--danger)' : 'var(--success)'}"></span></div>
			</td>
			<td>${low ? badge('Wuu yaraaday', 'danger') : badge('Ku filan', 'success')}</td>
			<td><div class="row-actions">
				<button title="Ku dar" data-stock="${i.id}|1"><i class='bx bx-plus'></i></button>
				<button class="danger" title="Ka jar" data-stock="${i.id}|-1"><i class='bx bx-minus'></i></button>
			</div></td>
		</tr>`;
	}).join('');

	const low = state.inventory.filter(i => i.qty < i.min).length;

	return `
	${pageHead('Bakhaarka', 'Agabka clinic-ka', `<button class="btn btn--primary" data-action="order"><i class='bx bx-cart-add'></i> Dalbo agab</button>`)}

	${low ? `<div class="card" style="margin-bottom:18px;border-left:4px solid var(--danger)">
		<div class="card__body" style="display:flex;align-items:center;gap:12px">
			<i class='bx bx-error' style="font-size:26px;color:var(--danger)"></i>
			<div><b>${low} shay ayaa ka hooseeya xadka ugu yar</b>
			<p style="font-size:13px;color:var(--text-2);margin-top:3px">Fadlan dalbo ka hor inta aan la gaadhin eber.</p></div>
		</div>
	</div>` : ''}

	<div class="card">
		<div class="table-wrap"><table>
			<thead><tr><th>Shay</th><th>Qayb</th><th>Tirada</th><th>Xadka ugu yar</th><th>Heerka</th><th>Xaalad</th><th style="text-align:right">Ficil</th></tr></thead>
			<tbody>${rows}</tbody>
		</table></div>
	</div>`;
}

/* ============================================================
   VIEW — STAFF (doctor only)
   ============================================================ */
function viewStaff() {
	const rows = STAFF.map(s => `
		<tr>
			<td>
				<div class="person">
					<span class="avatar">${initials(s.name)}</span>
					<div><b>${esc(s.name)}</b><span>${esc(s.id)}</span></div>
				</div>
			</td>
			<td>${esc(s.title)}</td>
			<td>${s.role === 'doctor' ? badge('Dhakhtar', 'brand') : badge('Shaqaale', 'info')}</td>
			<td>${esc(s.phone)}</td>
			<td>${esc(s.shift)}</td>
			<td>${s.status === 'On duty' ? badge('Shaqada joogaa', 'success') : badge('Maanta nasasho', 'muted')}</td>
		</tr>`).join('');

	return `
	${pageHead('Shaqaalaha', 'Kooxda clinic-ka', `<button class="btn btn--primary" data-action="new-staff"><i class='bx bx-user-plus'></i> Shaqaale cusub</button>`)}

	<div class="grid grid--3" style="margin-bottom:18px">
		<div class="card kpi"><div class="kpi__icon kpi__icon--brand"><i class='bx bxs-user-badge'></i></div><div><h3>${STAFF.filter(s => s.role === 'doctor').length}</h3><p>Dhakhaatiir</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--info"><i class='bx bxs-group'></i></div><div><h3>${STAFF.filter(s => s.role === 'staff').length}</h3><p>Shaqaale kale</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--ok"><i class='bx bx-check-shield'></i></div><div><h3>${STAFF.filter(s => s.status === 'On duty').length}</h3><p>Maanta shaqada jooga</p></div></div>
	</div>

	<div class="card">
		<div class="card__head"><div><h3>Liiska shaqaalaha</h3><p>Doorka ayaa go'aaminaya waxa la arki karo</p></div></div>
		<div class="table-wrap"><table>
			<thead><tr><th>Magac</th><th>Xilka</th><th>Door</th><th>Telefoon</th><th>Shift</th><th>Xaalad</th></tr></thead>
			<tbody>${rows}</tbody>
		</table></div>
	</div>`;
}

/* ============================================================
   VIEW — REPORTS (doctor only)
   ============================================================ */
function viewReports() {
	const week = weekSeries();
	const maxRev = Math.max(...week.map(r => r.value), 1);
	const total = week.reduce((s, r) => s + r.value, 0);

	const perService = SERVICES.map(s => {
		const count = state.treatments.filter(t => t.serviceId === s.id).length;
		return { name: s.name, count, revenue: count * s.price };
	}).sort((a, b) => b.revenue - a.revenue);
	const maxSvc = Math.max(...perService.map(s => s.revenue), 1);

	const cancelled = state.appointments.filter(a => a.status === 'Cancelled').length;
	const cancelRate = Math.round(cancelled / state.appointments.length * 100);

	/* per-doctor workload for the week */
	const perDoctor = STAFF.filter(u => u.role === 'doctor').map(d => {
		const appts = state.appointments.filter(a => a.doctor === d.name);
		const treats = state.treatments.filter(t => t.doctor === d.name);
		return {
			name: d.name, title: d.title,
			appts: appts.length,
			done: appts.filter(a => a.status === 'Completed').length,
			treats: treats.length,
			revenue: treats.reduce((sum, t) => sum + t.cost, 0)
		};
	});

	return `
	${pageHead('Warbixin', 'Falanqayn', `<button class="btn btn--ghost" data-action="export"><i class='bx bx-download'></i> PDF</button>`)}

	<div class="grid grid--3" style="margin-bottom:18px">
		<div class="card kpi"><div class="kpi__icon kpi__icon--ok"><i class='bx bx-trending-up'></i></div><div><h3>${money(total)}</h3><p>Dakhliga toddobaadka</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--brand"><i class='bx bx-calendar-check'></i></div><div><h3>${state.appointments.length}</h3><p>Ballamaha guud</p></div></div>
		<div class="card kpi"><div class="kpi__icon kpi__icon--info"><i class='bx bx-user-x'></i></div><div><h3>${cancelRate}%</h3><p>Heerka baajinta ballamaha</p></div></div>
	</div>

	<div class="grid grid--2">
		<div class="card">
			<div class="card__head"><div><h3>Dakhliga maalinlaha</h3><p>Toddobaadkan</p></div></div>
			<div class="card__body">
				<div class="bars">
					${week.map(r => `
						<div class="bars__col ${r.value === 0 ? 'muted' : ''}">
							<div class="bars__bar" style="height:${Math.max((r.value / maxRev) * 100, 3)}%" data-value="${money(r.value)}"></div>
							<small>${r.day}</small>
						</div>`).join('')}
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card__head"><div><h3>Adeegyada ugu dakhliga badan</h3></div></div>
			<div class="card__body">
				${perService.filter(s => s.count).map(s => `
					<div style="margin-bottom:15px">
						<div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
							<span>${esc(s.name)} <span style="color:var(--text-3)">· ${s.count}x</span></span>
							<b>${money(s.revenue)}</b>
						</div>
						<div class="meter"><span style="width:${(s.revenue / maxSvc) * 100}%"></span></div>
					</div>`).join('')}
			</div>
		</div>
	</div>

	<div class="card" style="margin-top:18px">
		<div class="card__head"><div><h3>Waxqabadka dhakhaatiirta</h3><p>Toddobaadkan</p></div></div>
		<div class="table-wrap"><table>
			<thead><tr><th>Dhakhtar</th><th>Xilka</th><th>Ballamo</th><th>La dhammeeyay</th><th>Daaweyn</th><th>Dakhli</th></tr></thead>
			<tbody>${perDoctor.map(d => `
				<tr>
					<td>
						<div class="person">
							<span class="avatar">${initials(d.name)}</span>
							<div><b>${esc(d.name)}</b></div>
						</div>
					</td>
					<td>${esc(d.title)}</td>
					<td>${d.appts}</td>
					<td>${d.done}</td>
					<td>${d.treats}</td>
					<td><b>${money(d.revenue)}</b></td>
				</tr>`).join('')}
			</tbody>
		</table></div>
	</div>`;
}

/* ============================================================
   VIEW — SETTINGS
   ============================================================ */
function viewSettings() {
	const isDoctor = state.role === 'doctor';
	return `
	${pageHead('Settings', 'Habaynta clinic-ka')}

	<div class="grid grid--2">
		<div class="card">
			<div class="card__head"><div><h3>Macluumaadka clinic-ka</h3><p>Hal clinic — hal diiwaan</p></div></div>
			<div class="card__body">
				<div class="form-grid">
					<div class="field span-2"><label>Magaca clinic-ka</label><input value="${esc(CLINIC.name)}" ${isDoctor ? '' : 'disabled'}></div>
					<div class="field span-2"><label>Cinwaan</label><input value="${esc(CLINIC.address)}" ${isDoctor ? '' : 'disabled'}></div>
					<div class="field"><label>Telefoon</label><input value="${esc(CLINIC.phone)}" ${isDoctor ? '' : 'disabled'}></div>
					<div class="field"><label>Saacadaha</label><input value="${esc(CLINIC.hours)}" ${isDoctor ? '' : 'disabled'}></div>
				</div>
				${isDoctor
					? `<button class="btn btn--primary" data-action="save-settings"><i class='bx bx-save'></i> Kaydi</button>`
					: `<p class="hint" style="font-size:13px;color:var(--text-2)"><i class='bx bx-lock-alt'></i> Dhakhtarka kaliya ayaa wax ka beddeli kara.</p>`}
			</div>
		</div>

		<div class="grid">
			<div class="card">
				<div class="card__head"><div><h3>Doorka aad haysato</h3></div></div>
				<div class="card__body">
					<div class="facts">
						<div class="fact"><small>Isticmaale</small><b>${esc(state.user)}</b></div>
						<div class="fact"><small>Door</small><b>${state.role === 'doctor' ? 'Dhakhtar' : 'Shaqaale'}</b></div>
					</div>
					<p style="font-size:13px;color:var(--text-2);margin-top:14px;line-height:1.7">
						<b>Dhakhtarka:</b> wax walba wuu arkaa — warbixinta, shaqaalaha, xusuusqorka caafimaad.<br>
						<b>Shaqaalaha:</b> ballamaha, bukaanka, lacagta iyo bakhaarka — laakiin ma beddeli karo diiwaanka caafimaad.
					</p>
					<button class="btn btn--ghost" style="margin-top:14px" data-action="switch-role">
						<i class='bx bx-transfer'></i> Beddel doorka (demo)
					</button>
				</div>
			</div>

			<div class="card">
				<div class="card__head"><div><h3>Muuqaalka</h3></div></div>
				<div class="card__body">
					<button class="btn btn--ghost" data-action="toggle-theme"><i class='bx bx-moon'></i> Beddel light / dark</button>
				</div>
			</div>
		</div>
	</div>`;
}

/* ============================================================
   FORMS / MODALS
   ============================================================ */
function modalShell(title, sub, body, footer, opts) {
	return openModal(`
		<div class="modal__head">
			<div><h3>${esc(title)}</h3><p>${esc(sub)}</p></div>
			<button class="btn btn--icon close" data-close><i class='bx bx-x'></i></button>
		</div>
		<div class="modal__body">${body}</div>
		<div class="modal__foot">${footer}</div>`, opts);
}

function patientOptions(selected) {
	return state.patients.map(p => `<option value="${p.id}" ${p.id === selected ? 'selected' : ''}>${esc(p.name)} — ${p.id}</option>`).join('');
}
function serviceOptions() {
	return SERVICES.map(s => `<option value="${s.id}">${esc(s.name)} (${s.duration}m · ${money(s.price)})</option>`).join('');
}
function doctorOptions() {
	return STAFF.filter(s => s.role === 'doctor').map(s => `<option>${esc(s.name)}</option>`).join('');
}

/* ---- new appointment ---- */
function formAppointment(patientId) {
	const m = modalShell('Ballan cusub', 'Buuxi faahfaahinta ballanka', `
		<div class="form-grid">
			<div class="field span-2"><label>Bukaanka</label><select id="fPatient">${patientOptions(patientId)}</select></div>
			<div class="field"><label>Taariikh</label><input type="date" id="fDate" value="${TODAY}"></div>
			<div class="field"><label>Waqti</label><input type="time" id="fTime" value="09:00"></div>
			<div class="field span-2"><label>Adeegga</label><select id="fService">${serviceOptions()}</select></div>
			<div class="field"><label>Dhakhtarka</label><select id="fDoctor">${doctorOptions()}</select></div>
			<div class="field"><label>Qolka</label><select id="fRoom"><option>Room 1</option><option>Room 2</option></select></div>
			<div class="field span-2"><label>Xusuusin</label><textarea id="fNote" placeholder="Tusaale: RCT kalfadhi 2aad"></textarea></div>
		</div>`,
		`<button class="btn btn--ghost" data-close>Jooji</button>
		 <button class="btn btn--primary" id="saveAppt"><i class='bx bx-check'></i> Kaydi ballanka</button>`);

	$('#saveAppt', m).addEventListener('click', () => {
		const date = $('#fDate', m).value;
		const time = $('#fTime', m).value;
		const doctor = $('#fDoctor', m).value;
		if (!date || !time) return toast('Taariikh iyo waqti waa lagama maarmaan.', 'error');

		const clash = state.appointments.some(a => a.date === date && a.time === time && a.doctor === doctor && a.status !== 'Cancelled');
		if (clash) return toast('Dhakhtarkan ballan buu ku leeyahay waqtigaas.', 'error');

		state.appointments.push({
			id: 'A-' + (500 + state.appointments.length + 1),
			patientId: $('#fPatient', m).value,
			date, time,
			serviceId: $('#fService', m).value,
			doctor, room: $('#fRoom', m).value,
			status: 'Confirmed',
			note: $('#fNote', m).value || '—'
		});
		state.filters.apptDay = date;
		closeModal();
		toast('Ballanka waa la kaydiyay.');
		renderNav();
		router();
	});
}

/* ---- new patient ---- */
function formPatient() {
	const m = modalShell('Bukaan cusub', 'Diiwaangeli bukaan cusub', `
		<div class="form-grid">
			<div class="field span-2"><label>Magaca oo saddex ah</label><input id="pName" placeholder="Magaca buuxa"></div>
			<div class="field"><label>Jinsi</label><select id="pGender"><option>Male</option><option>Female</option></select></div>
			<div class="field"><label>Da'</label><input type="number" id="pAge" min="1" max="120" value="25"></div>
			<div class="field"><label>Telefoon</label><input id="pPhone" placeholder="+252 6..."></div>
			<div class="field"><label>Nooca dhiigga</label><select id="pBlood"><option>O+</option><option>O-</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option></select></div>
			<div class="field span-2"><label>Cinwaan</label><input id="pAddress" placeholder="Xaafad, magaalo"></div>
			<div class="field span-2"><label>Xasaasiyad (allergies)</label><input id="pAllergy" value="Ma jiro"></div>
			<div class="field span-2"><label>Xusuusqor</label><textarea id="pNotes" placeholder="Taariikhda caafimaad ee muhiimka ah"></textarea></div>
		</div>`,
		`<button class="btn btn--ghost" data-close>Jooji</button>
		 <button class="btn btn--primary" id="savePatient"><i class='bx bx-check'></i> Diiwaangeli</button>`);

	$('#savePatient', m).addEventListener('click', () => {
		const name = $('#pName', m).value.trim();
		const phone = $('#pPhone', m).value.trim();
		if (!name || !phone) return toast('Magaca iyo telefoonka waa lagama maarmaan.', 'error');

		const id = 'P-' + (1042 + state.patients.length);
		state.patients.unshift({
			id, name,
			gender: $('#pGender', m).value,
			age: Number($('#pAge', m).value) || 0,
			phone,
			address: $('#pAddress', m).value || '—',
			blood: $('#pBlood', m).value,
			allergies: $('#pAllergy', m).value || 'Ma jiro',
			lastVisit: TODAY, balance: 0,
			notes: $('#pNotes', m).value || '—'
		});
		state.teeth[id] = {};
		closeModal();
		toast('Bukaanka waa la diiwaangeliyay: ' + id);
		location.hash = '#/patient/' + id;
	});
}

/* ---- new treatment ---- */
function formTreatment(patientId) {
	const m = modalShell('Daaweyn cusub', 'Ku dar qorshaha daaweynta', `
		<div class="form-grid">
			<div class="field span-2"><label>Bukaanka</label><select id="tPatient">${patientOptions(patientId)}</select></div>
			<div class="field"><label>Lambarka iliga (FDI)</label><input id="tTooth" placeholder="Tusaale: 46"></div>
			<div class="field"><label>Taariikh</label><input type="date" id="tDate" value="${TODAY}"></div>
			<div class="field span-2"><label>Adeegga</label><select id="tService">${serviceOptions()}</select></div>
			<div class="field"><label>Dhakhtarka</label><select id="tDoctor">${doctorOptions()}</select></div>
			<div class="field"><label>Xaalad</label><select id="tStatus"><option>Planned</option><option>In progress</option><option>Completed</option></select></div>
			<div class="field span-2"><label>Xusuusin</label><textarea id="tNote"></textarea></div>
		</div>`,
		`<button class="btn btn--ghost" data-close>Jooji</button>
		 <button class="btn btn--primary" id="saveTreat"><i class='bx bx-check'></i> Kaydi</button>`);

	$('#saveTreat', m).addEventListener('click', () => {
		const pid = $('#tPatient', m).value;
		const svc = serviceById($('#tService', m).value);
		state.treatments.unshift({
			id: 'T-' + (300 + state.treatments.length + 1),
			patientId: pid,
			tooth: $('#tTooth', m).value.trim() || '—',
			serviceId: svc.id,
			date: $('#tDate', m).value || TODAY,
			doctor: $('#tDoctor', m).value,
			status: $('#tStatus', m).value,
			cost: svc.price,
			note: $('#tNote', m).value || '—'
		});
		closeModal();
		toast('Daaweynta waa lagu daray.');
		router();
	});
}

/* ---- new invoice ---- */
function formInvoice() {
	const m = modalShell('Fatuurad cusub', 'Samee fatuurad bukaan', `
		<div class="form-grid">
			<div class="field span-2"><label>Bukaanka</label><select id="iPatient">${patientOptions()}</select></div>
			<div class="field span-2"><label>Adeegga</label><select id="iService">${serviceOptions()}</select></div>
			<div class="field"><label>Wadarta (${CLINIC.currency})</label><input type="number" id="iTotal" value="10" min="0"></div>
			<div class="field"><label>Hadda la bixiyay</label><input type="number" id="iPaid" value="0" min="0"></div>
			<div class="field span-2"><label>Habka lacag-bixinta</label><select id="iMethod"><option>Cash</option><option>EVC Plus</option><option>Card</option></select></div>
		</div>`,
		`<button class="btn btn--ghost" data-close>Jooji</button>
		 <button class="btn btn--primary" id="saveInv"><i class='bx bx-check'></i> Samee fatuurad</button>`);

	const sync = () => { $('#iTotal', m).value = serviceById($('#iService', m).value).price; };
	$('#iService', m).addEventListener('change', sync);
	sync();

	$('#saveInv', m).addEventListener('click', () => {
		const total = Number($('#iTotal', m).value) || 0;
		const paid = Math.min(Number($('#iPaid', m).value) || 0, total);
		const pid = $('#iPatient', m).value;
		state.invoices.unshift({
			id: 'INV-' + (2101 + state.invoices.length),
			patientId: pid, date: TODAY,
			items: serviceById($('#iService', m).value).name,
			total, paid,
			method: paid > 0 ? $('#iMethod', m).value : '—',
			status: paid >= total ? 'Paid' : paid > 0 ? 'Partial' : 'Unpaid'
		});
		const p = state.patients.find(x => x.id === pid);
		if (p) p.balance += (total - paid);
		closeModal();
		toast('Fatuuradda waa la sameeyay.');
		router();
	});
}

/* ---- take payment ---- */
function formPayment(invoiceId) {
	const inv = state.invoices.find(i => i.id === invoiceId);
	if (!inv) return;
	const due = inv.total - inv.paid;

	const m = modalShell('Lacag qaado', inv.id + ' — ' + patientById(inv.patientId).name, `
		<div class="facts" style="margin-bottom:18px">
			<div class="fact"><small>Wadarta</small><b>${money(inv.total)}</b></div>
			<div class="fact"><small>La bixiyay</small><b>${money(inv.paid)}</b></div>
			<div class="fact"><small>Hadhaaga</small><b style="color:var(--danger)">${money(due)}</b></div>
		</div>
		<div class="form-grid">
			<div class="field"><label>Lacagta la bixinayo</label><input type="number" id="payAmount" value="${due}" min="1" max="${due}"></div>
			<div class="field"><label>Habka</label><select id="payMethod"><option>Cash</option><option>EVC Plus</option><option>Card</option></select></div>
		</div>`,
		`<button class="btn btn--ghost" data-close>Jooji</button>
		 <button class="btn btn--primary" id="savePay"><i class='bx bx-check'></i> Xaqiiji lacagta</button>`);

	$('#savePay', m).addEventListener('click', () => {
		const amount = Math.min(Number($('#payAmount', m).value) || 0, due);
		if (amount <= 0) return toast('Geli lacag sax ah.', 'error');
		inv.paid += amount;
		inv.method = $('#payMethod', m).value;
		inv.status = inv.paid >= inv.total ? 'Paid' : 'Partial';
		const p = state.patients.find(x => x.id === inv.patientId);
		if (p) p.balance = Math.max(p.balance - amount, 0);
		closeModal();
		toast(money(amount) + ' waa la qaaday.');
		router();
	});
}

/* ============================================================
   EVENT WIRING
   ============================================================ */
function bindViewEvents(route) {
	/* delegated actions inside the view */
	view.addEventListener('click', onViewClick);

	if (route === 'appointments') {
		$('#dayFilter').addEventListener('change', e => { state.filters.apptDay = e.target.value; router(); });
		$$('#statusSeg button').forEach(b => b.addEventListener('click', () => {
			state.filters.apptStatus = b.dataset.status; router();
		}));
	}

	if (route === 'patients') {
		const box = $('#patientSearch');
		box.addEventListener('input', debounce(e => {
			state.filters.patientQuery = e.target.value;
			router();
			const again = $('#patientSearch');
			if (again) { again.focus(); again.setSelectionRange(again.value.length, again.value.length); }
		}, 220));
	}

	if (route === 'billing') {
		$$('#invoiceSeg button').forEach(b => b.addEventListener('click', () => {
			state.filters.invoiceStatus = b.dataset.invoiceStatus; router();
		}));
	}

	if (route === 'patient') {
		$$('#patientTabs button').forEach(btn => btn.addEventListener('click', () => {
			$$('#patientTabs button').forEach(b => b.classList.remove('active'));
			btn.classList.add('active');
			const map = { chart: 'tabChart', treatments: 'tabTreatments', appts: 'tabAppts', billing: 'tabBilling', notes: 'tabNotes' };
			Object.values(map).forEach(id => $('#' + id).classList.add('hidden'));
			$('#' + map[btn.dataset.tab]).classList.remove('hidden');
		}));
	}
}

function onViewClick(e) {
	const t = e.target.closest('[data-action], [data-goto-patient], [data-book], [data-appt-status], [data-appt-cancel], [data-pay], [data-stock], [data-tooth], [data-save-note], [data-call]');
	if (!t) return;

	/* open patient record */
	if (t.dataset.gotoPatient) { location.hash = '#/patient/' + t.dataset.gotoPatient; return; }
	if (t.dataset.book) { formAppointment(t.dataset.book); return; }
	if (t.dataset.call) { toast('Waa la wacayaa ' + t.dataset.call, 'info'); return; }

	/* odontogram: cycle tooth state */
	if (t.dataset.tooth) {
		const pid = location.hash.split('/')[2];
		const num = t.dataset.tooth;
		const cur = t.dataset.state || 'healthy';
		const next = TOOTH_ORDER[(TOOTH_ORDER.indexOf(cur) + 1) % TOOTH_ORDER.length];
		state.teeth[pid] = state.teeth[pid] || {};
		if (next === 'healthy') delete state.teeth[pid][num]; else state.teeth[pid][num] = next;
		t.dataset.state = next;
		t.title = `Ilig ${num} — ${next}`;
		$('i', t).className = 'bx ' + TOOTH_ICON[next];
		return;
	}

	/* appointment status flow */
	if (t.dataset.apptStatus) {
		const a = state.appointments.find(x => x.id === t.dataset.apptStatus);
		const flow = { 'Confirmed': 'Waiting', 'Waiting': 'In chair', 'In chair': 'Completed', 'Completed': 'Completed', 'Cancelled': 'Confirmed' };
		a.status = flow[a.status];
		toast(`${patientById(a.patientId).name.split(' ')[0]} → ${a.status}`, 'info');
		renderNav(); router(); return;
	}
	if (t.dataset.apptCancel) {
		const a = state.appointments.find(x => x.id === t.dataset.apptCancel);
		a.status = 'Cancelled';
		toast('Ballanka waa la baajiyay.', 'warn');
		renderNav(); router(); return;
	}

	if (t.dataset.pay) { formPayment(t.dataset.pay); return; }

	/* stock +/- */
	if (t.dataset.stock) {
		const [id, delta] = t.dataset.stock.split('|');
		const item = state.inventory.find(i => i.id === id);
		item.qty = Math.max(0, item.qty + Number(delta));
		renderNav(); router(); return;
	}

	if (t.dataset.saveNote) {
		const p = state.patients.find(x => x.id === t.dataset.saveNote);
		p.notes = $('#patientNote').value;
		toast('Xusuusqorka waa la kaydiyay.');
		return;
	}

	switch (t.dataset.action) {
		case 'new-appointment': formAppointment(); break;
		case 'new-patient':     formPatient(); break;
		case 'new-treatment':   formTreatment(t.dataset.patient); break;
		case 'new-invoice':     formInvoice(); break;
		case 'new-staff':       toast('Demo: foomka shaqaalaha lama xidhin backend.', 'info'); break;
		case 'order':           toast('Dalabka agabka waa la diray.', 'success'); break;
		case 'print':           window.print(); break;
		case 'export':          toast('Warbixinta waa la diyaarinayaa...', 'info'); break;
		case 'save-settings':   toast('Habaynta waa la kaydiyay.'); break;
		case 'toggle-theme':    toggleTheme(); break;
		case 'switch-role':     switchRole(); break;
	}
}

function debounce(fn, ms) {
	let id;
	return (...args) => { clearTimeout(id); id = setTimeout(() => fn(...args), ms); };
}

/* ============================================================
   CHROME BEHAVIOUR
   ============================================================ */
function toggleTheme() {
	const dark = document.body.classList.toggle('dark');
	localStorage.setItem('sc_theme', dark ? 'dark' : 'light');
	$('#themeBtn i').className = dark ? 'bx bx-sun' : 'bx bx-moon';
}

function switchRole() {
	state.role = state.role === 'doctor' ? 'staff' : 'doctor';
	state.user = state.role === 'doctor' ? 'Dr. Ismail C. Ahmed' : 'Nuradiin C. Cumar';
	localStorage.setItem('sc_role', state.role);
	localStorage.setItem('sc_user', state.user);
	renderNav(); renderIdentity();
	toast('Doorka waxa uu noqday: ' + (state.role === 'doctor' ? 'Dhakhtar' : 'Shaqaale'), 'info');
	location.hash = '#/dashboard';
	router();
}

$('#menuBtn').addEventListener('click', () => {
	if (window.innerWidth <= 992) document.body.classList.toggle('nav-open');
	else document.body.classList.toggle('collapsed');
});
$('#scrim').addEventListener('click', () => document.body.classList.remove('nav-open'));
$('#themeBtn').addEventListener('click', toggleTheme);

$('#bellBtn').addEventListener('click', () => {
	const low = state.inventory.filter(i => i.qty < i.min);
	const waiting = state.appointments.filter(a => a.date === TODAY && a.status === 'Waiting');
	modalShell('Digniinaha', 'Waxa maanta u baahan feejignaan', `
		<div class="timeline">
			${waiting.map(a => `<div class="timeline__item"><h4>Bukaan sugaya</h4><time>${a.time}</time><p>${esc(patientById(a.patientId).name)} — ${esc(serviceById(a.serviceId).name)}</p></div>`).join('')}
			${low.map(i => `<div class="timeline__item"><h4>Bakhaarka wuu yaraaday</h4><time>Hadda</time><p>${esc(i.name)} — ${i.qty}/${i.min} ${esc(i.unit)}</p></div>`).join('')}
			${(!waiting.length && !low.length) ? `<p style="color:var(--text-2)">Digniin cusub ma jirto.</p>` : ''}
		</div>`,
		`<button class="btn btn--primary" data-close>Waan fahmay</button>`);
});

$('#meBtn').addEventListener('click', () => { location.hash = '#/settings'; });

$('#globalSearch').addEventListener('keydown', e => {
	if (e.key !== 'Enter') return;
	const q = e.target.value.trim();
	if (!q) return;
	const hit = state.patients.find(p =>
		p.name.toLowerCase().includes(q.toLowerCase()) || p.id.toLowerCase() === q.toLowerCase() || p.phone.includes(q));
	if (hit) { location.hash = '#/patient/' + hit.id; e.target.value = ''; }
	else { state.filters.patientQuery = q; location.hash = '#/patients'; toast('Natiijooyinka raadinta: ' + q, 'info'); }
});

/* ============================================================
   BOOT
   ============================================================ */
(function boot() {
	if (localStorage.getItem('sc_theme') === 'dark') {
		document.body.classList.add('dark');
		$('#themeBtn i').className = 'bx bx-sun';
	}
	if (window.innerWidth <= 1280 && window.innerWidth > 992) document.body.classList.add('collapsed');

	renderNav();
	renderIdentity();
	window.addEventListener('hashchange', router);
	if (!location.hash) location.hash = '#/dashboard';
	router();
})();
