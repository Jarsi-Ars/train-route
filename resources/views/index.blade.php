<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Поиск поездов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">

        {{-- searchPath --}}
        <div class="col-md-6">
            <h2 class="mb-4">Поиск поезда</h2>

            <div id="alert-container"></div>

            <form id="train-form" data-search-url="{{ route('search') }}" novalidate>
                <div class="mb-3">
                    <label for="train_number" class="form-label">Номер поезда</label>
                    <input type="text" class="form-control" id="train_number" name="train_number" placeholder="Например: 016А" value="016А">
                    <div class="invalid-feedback" id="error-train_number"></div>
                </div>

                <div class="mb-3">
                    <label for="departure_station" class="form-label">Станция отправления</label>
                    <input type="text" class="form-control" id="departure_station" name="departure_station" placeholder="Например: Москва" value="Москва">
                    <div class="invalid-feedback" id="error-departure_station"></div>
                </div>

                <div class="mb-3">
                    <label for="arrival_station" class="form-label">Станция прибытия</label>
                    <input type="text" class="form-control" id="arrival_station" name="arrival_station" placeholder="Например: Санкт-Петербург" value="Санкт-Петербург">
                    <div class="invalid-feedback" id="error-arrival_station"></div>
                </div>

                <div class="row">
                    <div class="mb-3">
                        <label class="form-label">Дата отправления</label>
                        <input type="date" class="form-control" id="date_departure" name="date_departure">
                        <div class="invalid-feedback" id="error-date_departure"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" id="submit-btn">Найти</button>
            </form>
        </div>

        {{-- showPath --}}
        <div class="col-12 mt-5" id="show-train">
            <div id="route-container"></div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const form = document.getElementById('train-form');
    const submitBtn = document.getElementById('submit-btn');
    const alertContainer = document.getElementById('alert-container');
    const routeContainer = document.getElementById('route-container');

    const fields = ['train_number', 'departure_station', 'arrival_station', 'date_departure'];

    function clearErrors() {
        fields.forEach(field => {
            const input = document.getElementById(field);
            if (input) input.classList.remove('is-invalid');
            const errorEl = document.getElementById('error-' + field);
            if (errorEl) errorEl.textContent = '';
        });
        alertContainer.innerHTML = '';
    }

    function showFieldErrors(errors) {
        const mapped = { ...errors };
        if (mapped.day || mapped.month) {
            mapped.date_departure = [...(mapped.day ?? []), ...(mapped.month ?? [])];
            delete mapped.day;
            delete mapped.month;
        }
        for (const [field, messages] of Object.entries(mapped)) {
            const input = document.getElementById(field);
            const errorEl = document.getElementById('error-' + field);
            if (input && errorEl) {
                input.classList.add('is-invalid');
                errorEl.textContent = messages[0];
            }
        }
    }

    function showAlert(message) {
        alertContainer.innerHTML = `
<div class="alert alert-warning fade show alert-dismissible" role="alert">
    <h5 class="alert-heading">Ошибка.</h5>
    <hr>
    <p>${message}</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>`
        ;
    }

    function renderStations(stations) {
        if (!stations || stations.length === 0) {
            routeContainer.innerHTML = '<p class="text-muted">Маршрут не найден</p>';
            return;
        }

        const rows = stations.map(s => `
        <tr>
            <td>${s.indexItem}</td>
            <td>${s.station}</td>
            <td>${s.arrival_time ?? ''}</td>
            <td>${s.departure_time ?? ''}</td>
            <td>${s.stop_time}</td>
        </tr>`).join('');

        routeContainer.innerHTML = `
        <h4 class="mb-3">Маршрут следования</h4>
        <table class="table table-bordered table-striped table-center">
            <thead class="table-primary" align="center"">
                <tr>
                    <th>#</th>
                    <th>Станция</th>
                    <th>Прибытие</th>
                    <th>Отправление</th>
                    <th>Стоянка (мин)</th>
                </tr>
            </thead>
            <tbody align="center">${rows}</tbody>
        </table>`;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        routeContainer.innerHTML = '';

        const dateValue = document.getElementById('date_departure').value;
        const [, month, day] = dateValue ? dateValue.split('-') : ['', '', ''];

        const data = {
            train_number: document.getElementById('train_number').value,
            departure_station: document.getElementById('departure_station').value,
            arrival_station: document.getElementById('arrival_station').value,
            day,
            month,
        };

        submitBtn.disabled = true;

        const searchUrl = form.dataset.searchUrl;

        try {
            const response = await fetch(searchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data),
            });

            const json = await response.json();

            if (response.ok) {
                renderStations(json.stations ?? []);
            } else if (response.status === 422) {
                showFieldErrors(json.errors ?? {});
            } else {
                showAlert(json.message ?? 'Произошла ошибка на сервере.');
            }
        } catch {
            showAlert('Ошибка соединения с сервером.');
        } finally {
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>
