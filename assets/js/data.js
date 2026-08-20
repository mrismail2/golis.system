/* ============================================================
   SmileCare — demo data layer
   Kept in-memory + mirrored to localStorage so the prototype
   feels like a real single-clinic system.
   ============================================================ */

const CLINIC = {
	name: 'SmileCare Dental Clinic',
	address: 'Taleex St, Hodan, Muqdisho',
	phone: '+252 61 555 0110',
	hours: '08:00 – 20:00 (Sat – Thu)',
	currency: '$'
};

const SERVICES = [
	{ id: 'S1', name: 'Consultation / Baaritaan',      duration: 20, price: 10 },
	{ id: 'S2', name: 'Scaling & Polishing',            duration: 40, price: 35 },
	{ id: 'S3', name: 'Composite Filling',              duration: 45, price: 30 },
	{ id: 'S4', name: 'Root Canal (RCT)',               duration: 90, price: 120 },
	{ id: 'S5', name: 'Tooth Extraction',               duration: 30, price: 25 },
	{ id: 'S6', name: 'Crown / Taaj',                   duration: 60, price: 180 },
	{ id: 'S7', name: 'Teeth Whitening',                duration: 50, price: 90 },
	{ id: 'S8', name: 'Braces Adjustment',              duration: 30, price: 45 }
];

const STAFF = [
	{ id: 'U1', name: 'Dr. Ismail C. Ahmed',   role: 'doctor',   title: 'Lead Dentist',        phone: '+252 61 555 0110', shift: '08:00 – 16:00', status: 'On duty' },
	{ id: 'U2', name: 'Dr. Shucayb M. Aadan',  role: 'doctor',   title: 'Orthodontist',        phone: '+252 61 555 0120', shift: '12:00 – 20:00', status: 'On duty' },
	{ id: 'U3', name: 'Nuradiin C. Cumar',     role: 'staff',    title: 'Receptionist',        phone: '+252 61 555 0130', shift: '08:00 – 16:00', status: 'On duty' },
	{ id: 'U4', name: 'Maxamuud M. Cabdilaahi',role: 'staff',    title: 'Dental Assistant',    phone: '+252 61 555 0140', shift: '10:00 – 18:00', status: 'On duty' },
	{ id: 'U5', name: 'Hodan A. Yuusuf',       role: 'staff',    title: 'Cashier / Billing',   phone: '+252 61 555 0150', shift: '08:00 – 16:00', status: 'Off today' }
];

const PATIENTS = [
	{
		id: 'P-1042', name: 'Ismail Cabdiraxmaan Ahmed', gender: 'Male', age: 27,
		phone: '+252 61 700 1042', address: 'Hodan, Muqdisho', blood: 'O+',
		allergies: 'Penicillin', lastVisit: '2026-08-20', balance: 0,
		notes: 'Xasaasiyad penicillin ah. Waxa loo qorayaa Amoxil beddelkiis.'
	},
	{
		id: 'P-1043', name: 'Shucayb Muuse Aadan', gender: 'Male', age: 34,
		phone: '+252 61 700 1043', address: 'Wadajir, Muqdisho', blood: 'A+',
		allergies: 'Ma jiro', lastVisit: '2026-08-18', balance: 60,
		notes: 'RCT socota — kalfadhiga 2aad ee ilkaha 46.'
	},
	{
		id: 'P-1044', name: 'Nuradiin Cabdiraxmaan Cumar', gender: 'Male', age: 22,
		phone: '+252 61 700 1044', address: 'Kaaraan, Muqdisho', blood: 'B+',
		allergies: 'Ma jiro', lastVisit: '2026-08-11', balance: 0,
		notes: 'Braces — la habeeyo 4 toddobaad kasta.'
	},
	{
		id: 'P-1045', name: 'Maxamuud Maxamed Cabdilaahi', gender: 'Male', age: 41,
		phone: '+252 61 700 1045', address: 'Yaaqshiid, Muqdisho', blood: 'O-',
		allergies: 'Latex', lastVisit: '2026-07-29', balance: 25,
		notes: 'Cadaadis dhiig sare — la eego ka hor qalliinka.'
	},
	{
		id: 'P-1046', name: 'Hodan Cali Yuusuf', gender: 'Female', age: 30,
		phone: '+252 61 700 1046', address: 'Shibis, Muqdisho', blood: 'AB+',
		allergies: 'Ma jiro', lastVisit: '2026-08-20', balance: 0,
		notes: 'Nadaafad 6 biloodle ah.'
	},
	{
		id: 'P-1047', name: 'Faadumo Cabdi Warsame', gender: 'Female', age: 19,
		phone: '+252 61 700 1047', address: 'Dharkeynley, Muqdisho', blood: 'A-',
		allergies: 'Ma jiro', lastVisit: '2026-08-20', balance: 120,
		notes: 'Ilig garaadka (wisdom) — qalliin la qorsheeyay.'
	},
	{
		id: 'P-1048', name: 'Cabdirisaaq Xasan Nuur', gender: 'Male', age: 52,
		phone: '+252 61 700 1048', address: 'Bondhere, Muqdisho', blood: 'B-',
		allergies: 'Ibuprofen', lastVisit: '2026-06-30', balance: 0,
		notes: 'Sonkorow nooca 2aad — daaweyn tartiib ah.'
	},
	{
		id: 'P-1049', name: 'Sagal Maxamed Diiriye', gender: 'Female', age: 25,
		phone: '+252 61 700 1049', address: 'Waaberi, Muqdisho', blood: 'O+',
		allergies: 'Ma jiro', lastVisit: '2026-08-20', balance: 0,
		notes: 'Xanuun ilig 24 — la baarayo.'
	}
];

const TODAY = '2026-08-20';

const APPOINTMENTS = [
	{ id: 'A-501', patientId: 'P-1042', date: TODAY, time: '08:30', serviceId: 'S2', doctor: 'Dr. Ismail C. Ahmed',  status: 'Completed',  room: 'Room 1', note: 'Nadaafad caadi ah' },
	{ id: 'A-502', patientId: 'P-1049', date: TODAY, time: '09:00', serviceId: 'S1', doctor: 'Dr. Ismail C. Ahmed',  status: 'Completed',  room: 'Room 1', note: 'Xanuun ilig 24' },
	{ id: 'A-503', patientId: 'P-1043', date: TODAY, time: '10:00', serviceId: 'S4', doctor: 'Dr. Ismail C. Ahmed',  status: 'In chair',   room: 'Room 2', note: 'RCT kalfadhi 2aad' },
	{ id: 'A-504', patientId: 'P-1046', date: TODAY, time: '11:00', serviceId: 'S7', doctor: 'Dr. Shucayb M. Aadan', status: 'Completed',  room: 'Room 1', note: 'Whitening' },
	{ id: 'A-505', patientId: 'P-1047', date: TODAY, time: '12:30', serviceId: 'S5', doctor: 'Dr. Ismail C. Ahmed',  status: 'Waiting',    room: 'Room 2', note: 'Ilig garaadka 38' },
	{ id: 'A-506', patientId: 'P-1044', date: TODAY, time: '14:00', serviceId: 'S8', doctor: 'Dr. Shucayb M. Aadan', status: 'Confirmed',  room: 'Room 1', note: 'Braces adjustment' },
	{ id: 'A-507', patientId: 'P-1045', date: TODAY, time: '15:30', serviceId: 'S3', doctor: 'Dr. Ismail C. Ahmed',  status: 'Confirmed',  room: 'Room 2', note: 'Filling ilig 36' },
	{ id: 'A-508', patientId: 'P-1048', date: TODAY, time: '16:30', serviceId: 'S1', doctor: 'Dr. Shucayb M. Aadan', status: 'Cancelled',  room: 'Room 1', note: 'Bukaanku wuu baajiyay' },
	{ id: 'A-509', patientId: 'P-1042', date: '2026-08-21', time: '09:00', serviceId: 'S3', doctor: 'Dr. Ismail C. Ahmed',  status: 'Confirmed', room: 'Room 1', note: 'Filling ilig 16' },
	{ id: 'A-510', patientId: 'P-1049', date: '2026-08-21', time: '10:30', serviceId: 'S4', doctor: 'Dr. Ismail C. Ahmed',  status: 'Confirmed', room: 'Room 2', note: 'RCT bilow' },
	{ id: 'A-511', patientId: 'P-1046', date: '2026-08-22', time: '11:00', serviceId: 'S2', doctor: 'Dr. Shucayb M. Aadan', status: 'Confirmed', room: 'Room 1', note: 'Scaling' }
];

const TREATMENTS = [
	{ id: 'T-301', patientId: 'P-1043', tooth: '46', serviceId: 'S4', date: '2026-08-18', doctor: 'Dr. Ismail C. Ahmed',  status: 'In progress', cost: 120, note: 'Kalfadhi 1/3 la dhammeeyay' },
	{ id: 'T-302', patientId: 'P-1042', tooth: '16', serviceId: 'S3', date: '2026-08-14', doctor: 'Dr. Ismail C. Ahmed',  status: 'Planned',     cost: 30,  note: 'Caries dhexdhexaad ah' },
	{ id: 'T-303', patientId: 'P-1047', tooth: '38', serviceId: 'S5', date: '2026-08-20', doctor: 'Dr. Ismail C. Ahmed',  status: 'Planned',     cost: 25,  note: 'Impacted — X-ray la qaaday' },
	{ id: 'T-304', patientId: 'P-1046', tooth: '—',  serviceId: 'S7', date: '2026-08-19', doctor: 'Dr. Shucayb M. Aadan', status: 'Completed',   cost: 90,  note: 'Natiijo wanaagsan' },
	{ id: 'T-305', patientId: 'P-1044', tooth: '—',  serviceId: 'S8', date: '2026-08-11', doctor: 'Dr. Shucayb M. Aadan', status: 'Completed',   cost: 45,  note: 'Wire la beddelay' },
	{ id: 'T-306', patientId: 'P-1045', tooth: '36', serviceId: 'S3', date: '2026-07-29', doctor: 'Dr. Ismail C. Ahmed',  status: 'Completed',   cost: 30,  note: 'Composite' },
	{ id: 'T-307', patientId: 'P-1049', tooth: '24', serviceId: 'S1', date: '2026-08-20', doctor: 'Dr. Ismail C. Ahmed',  status: 'Completed',   cost: 10,  note: 'Baaritaan + X-ray' }
];

const INVOICES = [
	{ id: 'INV-2101', patientId: 'P-1047', date: '2026-08-20', items: 'Extraction 38', total: 120, paid: 0,   method: '—',      status: 'Unpaid' },
	{ id: 'INV-2100', patientId: 'P-1049', date: '2026-08-20', items: 'Consultation + X-ray', total: 35,  paid: 35,  method: 'EVC Plus', status: 'Paid' },
	{ id: 'INV-2102', patientId: 'P-1042', date: '2026-08-20', items: 'Scaling & Polishing', total: 35,  paid: 35,  method: 'Cash',     status: 'Paid' },
	{ id: 'INV-2099', patientId: 'P-1046', date: '2026-08-20', items: 'Teeth Whitening', total: 90,  paid: 90,  method: 'EVC Plus', status: 'Paid' },
	{ id: 'INV-2098', patientId: 'P-1043', date: '2026-08-18', items: 'RCT session 1', total: 120, paid: 60,  method: 'Cash',     status: 'Partial' },
	{ id: 'INV-2097', patientId: 'P-1042', date: '2026-08-14', items: 'Scaling & Polishing', total: 35,  paid: 35,  method: 'Cash',     status: 'Paid' },
	{ id: 'INV-2096', patientId: 'P-1044', date: '2026-08-11', items: 'Braces Adjustment', total: 45,  paid: 45,  method: 'EVC Plus', status: 'Paid' },
	{ id: 'INV-2095', patientId: 'P-1045', date: '2026-07-29', items: 'Composite Filling', total: 30,  paid: 5,   method: 'Cash',     status: 'Partial' }
];

const INVENTORY = [
	{ id: 'IT-01', name: 'Composite Resin A2',   category: 'Restorative', qty: 12, min: 10, unit: 'syringe' },
	{ id: 'IT-02', name: 'Lidocaine 2%',         category: 'Anesthetic',  qty: 6,  min: 15, unit: 'box' },
	{ id: 'IT-03', name: 'Latex Gloves (M)',     category: 'Disposable',  qty: 38, min: 20, unit: 'box' },
	{ id: 'IT-04', name: 'Face Masks',           category: 'Disposable',  qty: 9,  min: 20, unit: 'box' },
	{ id: 'IT-05', name: 'Gutta Percha Points',  category: 'Endodontic',  qty: 24, min: 10, unit: 'pack' },
	{ id: 'IT-06', name: 'Fluoride Varnish',     category: 'Preventive',  qty: 17, min: 8,  unit: 'tube' },
	{ id: 'IT-07', name: 'X-ray Films',          category: 'Imaging',     qty: 4,  min: 12, unit: 'pack' }
];

/* Weekly revenue (Sat → Fri). `Khm` is today, so app.js recomputes it
   live from the invoices that have actually been paid. */
const REVENUE_WEEK = [
	{ day: 'Sab', value: 240 },
	{ day: 'Axd', value: 310 },
	{ day: 'Isn', value: 180 },
	{ day: 'Tal', value: 420 },
	{ day: 'Arb', value: 365 },
	{ day: 'Khm', value: 0 },
	{ day: 'Jim', value: 0 }
];

/* Odontogram default states per patient: FDI tooth number -> state */
const TOOTH_STATES = {
	'P-1042': { '16': 'caries', '26': 'filled', '36': 'filled' },
	'P-1043': { '46': 'crown', '47': 'caries', '18': 'missing' },
	'P-1044': { '11': 'filled', '21': 'filled' },
	'P-1045': { '36': 'treated', '37': 'caries', '48': 'missing' },
	'P-1046': {},
	'P-1047': { '38': 'caries', '48': 'caries' },
	'P-1048': { '17': 'missing', '27': 'missing', '46': 'crown' },
	'P-1049': { '24': 'caries' }
};

const ACTIVITY = [
	{ time: '16:10', title: 'Fatuurada INV-2101 la sameeyay',  text: 'Faadumo C. Warsame — $120 (Unpaid)', by: 'Hodan A. Yuusuf' },
	{ time: '15:20', title: 'Ballan cusub la qabtay',           text: 'Maxamuud M. Cabdilaahi — Filling, 15:30', by: 'Nuradiin C. Cumar' },
	{ time: '14:05', title: 'Daaweyn la dhammeeyay',            text: 'Hodan C. Yuusuf — Teeth Whitening', by: 'Dr. Shucayb M. Aadan' },
	{ time: '11:45', title: 'Diiwaan bukaan cusub',             text: 'Sagal M. Diiriye — P-1049', by: 'Nuradiin C. Cumar' },
	{ time: '09:30', title: 'Digniin bakhaar',                  text: 'Lidocaine 2% wuu yaraaday (6 box)', by: 'System' }
];
