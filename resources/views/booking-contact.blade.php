<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking contact | Vatican Operations</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <style>
        .contact-page { display: grid; gap: 24px; }
        .contact-page .panel { border-radius: 18px; padding: 28px; background: linear-gradient(145deg, #eef2ff 0%, #e0e7ff 100%); border: 1px solid #c7d2fe; border-top: 5px solid #6366f1; box-shadow: 0 10px 30px rgba(79, 70, 229, .12); }
        .contact-help { color: #4338ca; font-size: 13px; margin: 0 0 18px; }
        .contact-form { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; }
        .contact-form label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: #4338ca; text-transform: uppercase; letter-spacing: .05em; }
        .contact-form input, .contact-form select { padding: 11px 14px; border: 1px solid #c7d2fe; border-radius: 12px; background: #fff; font: inherit; font-size: 14px; color: #17202b; }
        .contact-form-footer { grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 4px; }
        .contact-button { border: 0; border-radius: 999px; padding: 11px 24px; font: inherit; font-weight: 700; cursor: pointer; color: #fff; background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 8px 20px rgba(79, 70, 229, .3); }
        .contact-flash { padding: 14px 18px; border-radius: 12px; font-weight: 600; }
        .contact-flash.success { background: #d1fae5; color: #065f46; }
        .contact-flash.error { background: #fee2e2; color: #b91c1c; }
        @media (max-width: 640px) { .contact-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar', ['active' => 'booking-contact'])
    <main class="main-content contact-page">
        <header class="topbar"><div><p class="eyebrow">TICKET OFFICE / ADMIN</p><h1>Booking contact</h1></div></header>

        @if (session('success'))<div class="contact-flash success">✓ {{ session('success') }}</div>@endif
        @if (session('error'))<div class="contact-flash error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="contact-flash error">{{ $errors->first() }}</div>@endif

        <section class="panel">
            <div class="panel-heading"><div><p class="eyebrow">MANAGER DATA</p><h3>Contact person for the reservation</h3></div></div>
            <p class="contact-help">These details are reused for every booking's "Manager data" step (the mandatory contact-person form on the ticket site). Fill them in once here.</p>
            <form class="contact-form" method="POST" action="{{ route('booking-contact.update') }}">
                @csrf
                @method('PATCH')
                <label>Surname<input type="text" name="surname" maxlength="100" value="{{ old('surname', $contact['surname']) }}"></label>
                <label>Name<input type="text" name="name" maxlength="100" value="{{ old('name', $contact['name']) }}"></label>
                <label>Sex
                    <select name="sex">
                        <option value="" @selected(! $contact['sex'])>Select</option>
                        <option value="Male" @selected($contact['sex'] === 'Male')>Male</option>
                        <option value="Female" @selected($contact['sex'] === 'Female')>Female</option>
                    </select>
                </label>
                <label>Country<input type="text" name="country" maxlength="100" value="{{ old('country', $contact['country']) }}"></label>
                <label>City<input type="text" name="city" maxlength="100" value="{{ old('city', $contact['city']) }}"></label>
                <label>Birthdate<input type="date" name="birthdate" value="{{ old('birthdate', $contact['birthdate']) }}"></label>
                <label>Email<input type="email" name="email" maxlength="190" value="{{ old('email', $contact['email']) }}"></label>
                <label>Mobile number<input type="text" name="mobile" maxlength="30" value="{{ old('mobile', $contact['mobile']) }}"></label>
                <label>Language<input type="text" name="language" maxlength="50" value="{{ old('language', $contact['language']) }}" placeholder="e.g. English"></label>
                <div class="contact-form-footer"><button type="submit" class="contact-button">Save</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
