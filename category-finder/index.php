<?
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Поиск разделов");
?>
<link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.2/css/buttons.dataTables.css">

<style>
	div.dt-processing {
		position: fixed;
	}
	.filter-form {
		display: flex;
		justify-content: flex-start;
		gap: 10px;
	}
	.filter-group {
		display: flex;
		flex-direction: column;
		justify-content: end;
		align-items: start;
		flex-grow: 1;
	}
	
	select {
		width: 100%;
	}
	
	th {
		white-space: nowrap;
	}
</style>

<div class="filter-form">
	<div class="filter-group">
		<label>ИнфоБлок ID</label>
		<input type="number" placeholder="ИнфоБлок" id="filter_iblock" value="24">
	</div>
	<div class="filter-group">
		<label data-tippy-content="Уровень вложенности, с которого происходит поиск. Категории уровнем выше не будут входить в результат поиска.">Уровень (?)</label>
		<input type="number" min="1" placeholder="Уровень" id="filter_level" value="1">
	</div>
	<div class="filter-group">
		<label>Кол-во элементов</label>
		<input type="number" placeholder="Количество элементов" id="filter_cnt" value="0">		
	</div>
	<div class="filter-group">
		<label>Активность</label>
		<select id="filter_active">
			<option value="">Все</option>
			<option value="1" selected>Да</option>
			<option value="0">Нет</option>
		</select>
	</div>
	<div class="filter-group">
		<label>Без редиректа</label>
		<select id="filter_redirect">
			<option value="">Все</option>
			<option value="1" selected>Да</option>
			<option value="0">Нет</option>
		</select>
	</div>
	<div class="filter-group">
		<label id="without-prod-hide" data-tippy-content="Ставится галочка и показывается список категорий, который мы считаем корректным, чаще всего это родительские категории, которые выводят в себе товары с подкатегорий.">Без товаров (?)</label>
		<select id="filter_without_prod">
			<option value="">Все</option>
			<option value="1">Да</option>
			<option value="0">Нет</option>
		</select>
	</div>
	<div class="filter-group">
		<input type="submit" id="filter_btn" value="Отправить">
	</div>
</div>

<hr>

<table id="example" class="display compact" style="width:100%">
    <thead>
    <tr>
        <th>#</th>
        <th>Уровень</th>
        <th>ID</th>
        <th>Кол-во</th>
        <th>Акт.</th>
        <th>Название</th>
        <th>Ссылка</th>
		<th>Без товаров</th>
    </tr>
    </thead>
</table>


<script src="https://cdn.datatables.net/2.2.2/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.html5.min.js"></script>

<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>

<script>
    let table = $('#example').DataTable({
        "processing": true,
        "serverSide": true,
		language: {
			"info": "",
			"infoEmpty": "",
			"infoFiltered": "Всего записей: _MAX_",
		},
        ajax: {
            url: 'scripts/server.php',
            type: 'POST',
            data: function (d) {
                d.filter_iblock = $('#filter_iblock').val();
                d.filter_cnt = $('#filter_cnt').val();
                d.filter_level = $('#filter_level').val();
                d.filter_active = $('#filter_active').val();
                d.filter_redirect = $('#filter_redirect').val();
                d.filter_without_prod = $('#filter_without_prod').val();
            }
        },
        paging: false,
        ordering: false,
        searching: false,
        autoWidth: false,
        "dom": 'Bifrt',
        "buttons": [
            {
                extend: 'excelHtml5',
                text: 'Скачать Excel',
            }
        ],
		columnDefs: [
			{
				render: function (data) {
					
					let checkbox = $("<input />", {
						class: 'without-prod-check',
						type: 'checkbox',
						value: '1',
					});
					
					checkbox.attr('checked', data);
					
					return checkbox[0].outerHTML;
				},
				targets: -1,
			},
		],
    });

    $('#filter_btn').on('click', function () {
        table.draw();
    });
	
	$('#example tbody').on('click', '.without-prod-check', function () {
		let self = $(this);
		let data = table.row(self.parents('tr')).data();
		let ID = data[2];
		let state = self.prop('checked');
					
		$.ajax({
			url: '/category-finder/scripts/update.php',
			type: 'post',
			dataType: 'html',
			data: { ID: ID, WITHOUT_PROD: state }
		});
	});
	
	tippy('[data-tippy-content]');
	
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
