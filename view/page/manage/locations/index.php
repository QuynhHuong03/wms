<?php
include_once(__DIR__ . "/../../../../controller/clocation.php");
$c = new CLocation();
$locations = $c->listLocations();
// Normalize
if ($locations === false) $locations = [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý Location</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* NEW STYLES - replace existing style block */
        :root{
            --bg:#f6f7fb;
            --card:#ffffff;
            --muted:#6b7280;
            --primary:#2563eb;
            --accent:#10b981;
            --danger:#ef4444;
            --border:#e6e7eb;
            --shadow: 0 6px 18px rgba(16,24,40,0.06);
        }
        html,body{height:100%}
        body{
            font-family:Inter, "Segoe UI", Roboto, Arial, sans-serif;
            background:var(--bg);
            padding:28px;
            margin:0;
            color:#0b1220;
        }
        .panel{
            background:var(--card);
            border-radius:12px;
            padding:18px;
            box-shadow:var(--shadow);
            max-width:1220px;
            margin:0 auto;
        }
        .header{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px}
        .header h2{margin:0;font-weight:600;font-size:20px}
        .muted{color:var(--muted);font-size:13px}
        .zones{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px}
        @media(max-width:1100px){.zones{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:700px){.zones{grid-template-columns:1fr}}
        .zone{
            background:linear-gradient(180deg,rgba(255,255,255,0.98),var(--card));
            border:1px solid var(--border);
            border-radius:10px;
            padding:14px;
            min-height:340px;
            display:flex;
            flex-direction:column;
            gap:12px;
        }
        .zone h4{margin:0;font-size:16px}
        .zone .zone-meta{display:flex;justify-content:space-between;align-items:center;gap:8px}
        .rack-row{display:flex;flex-direction:column;gap:12px}
        /* Each rack card */
        .rack{
            border:1px dashed #e9eef6;
            padding:12px;
            border-radius:8px;
            background:#fbfdff;
        }
        .rack h5{margin:0 0 8px;font-size:14px;color:#0f172a}
        .rack .rack-meta{font-size:12px;color:var(--muted);margin-bottom:8px}
        /* Bins grid inside a rack */
        .bins{display:grid;grid-template-columns:repeat(auto-fit,minmax(84px,1fr));gap:10px}
        .bin{
            border:1px solid var(--border);
            border-radius:8px;
            padding:8px;
            text-align:center;
            background:#fff;
            transition:transform .12s ease,box-shadow .12s ease;
            min-height:64px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            gap:6px;
        }
        .bin:hover{transform:translateY(-4px);box-shadow:0 6px 14px rgba(16,24,40,0.06)}
        .bin.full{border-color:var(--danger);background:linear-gradient(180deg,#fff6f6,#fff)}
        .bin.empty{opacity:.9;background:linear-gradient(180deg,#fbfbff,#fff)}
        .bin .code{font-weight:700;font-size:13px;margin-bottom:4px}
        .bin .meta{font-size:12px;color:var(--muted)}
        .controls{display:flex;gap:8px;align-items:center}
        .btn{
            background:var(--primary);
            color:#fff;
            padding:8px 12px;
            border-radius:8px;
            border:none;
            cursor:pointer;
            font-weight:600;
            box-shadow:0 6px 14px rgba(37,99,235,0.08);
        }
        .btn.secondary{background:var(--accent)}
        .btn.small{padding:6px 8px;font-size:13px;border-radius:6px}
        .btn.ghost{background:transparent;color:var(--primary);box-shadow:none;border:1px solid rgba(37,99,235,0.10)}
        form.inline{display:flex;gap:8px}
        .preview-zone{display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap}
        .preview-rack{
            border:1px dashed #dbeafe;
            padding:10px;
            background:#fff;
            border-radius:8px;
            min-width:120px;
        }
        .preview-rack .rack-title{font-weight:700;margin-bottom:8px;text-align:center}
        .preview-bins{display:grid;grid-auto-rows:28px;gap:8px}
        .preview-bin{
            width:40px;height:28px;border:1px solid #e6edf8;border-radius:6px;
            display:flex;align-items:center;justify-content:center;background:#f8fafc;font-size:12px
        }
        small.muted{color:var(--muted);font-size:12px}
    </style>
    <style>
        /* Modal & toast styles */
        .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:1200}
        .modal[aria-hidden="false"]{display:flex}
        .modal-backdrop{position:fixed;inset:0;background:rgba(11,18,32,0.45)}
        .modal-panel{background:#fff;padding:16px;border-radius:10px;z-index:1210;max-width:480px;width:94%;box-shadow:0 12px 32px rgba(2,6,23,0.2)}
        #toastContainer{position:fixed;right:18px;bottom:18px;z-index:1300}
        .toast{background:#111827;color:#fff;padding:10px 14px;border-radius:8px;margin-top:8px;box-shadow:0 8px 20px rgba(2,6,23,0.12)}
        .toast.success{background:#059669}
        .toast.error{background:#dc2626}
    </style>
</head>
<body>
    <!-- Toast container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- Add Bin Modal -->
    <div id="addBinModal" class="modal" role="dialog" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeAddBinModal()"></div>
        <div class="modal-panel" role="document" aria-labelledby="addBinTitle">
            <h3 id="addBinTitle">Thêm Bin</h3>
            <form id="addBinForm" onsubmit="return submitAddBinModal(event)">
                <input type="hidden" id="modalZoneId">
                <input type="hidden" id="modalRackId">
                <div style="margin-bottom:8px">
                    <label> Tên bin </label><br>
                    <input id="modalBinName" type="text" placeholder="Ví dụ: Bin hàng nhỏ" style="width:100%;padding:8px;border:1px solid #e6e7eb;border-radius:6px">
                </div>
                <div style="margin-bottom:8px" class="muted">Mã bin và code sẽ được hệ thống tự sinh (B1, B2, ... và Zone-Rack-Bin).</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <label style="display:flex;align-items:center;margin-right:12px"><input id="modalAutoRack" type="checkbox" style="margin-right:6px"> Tự tạo Rack tiếp theo</label>
                    <label style="display:flex;align-items:center"><input id="modalForce" type="checkbox" style="margin-right:6px"> Force create</label>
                    <div style="margin-left:auto">
                        <button type="button" class="btn small ghost" onclick="closeAddBinModal()">Hủy</button>
                        <button type="submit" class="btn small">Thêm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
	<div class="panel">
		<div class="header">
			<div>
				<h2>Quản lý Location (Zone/Rack/Bin)</h2>
				<div class="muted">Zone max: 3 | Rack/zone max: 4 | Bin/rack max: 10 | Capacity/bin max: 20</div>
			</div>
			<div class="controls">
                <button class="btn small" id="addZoneBtn">Thêm Zone</button>
			</div>
		</div>

		<div class="zones" id="zonesContainer">
			<?php if (empty($locations)): ?>
				<div class="muted">Chưa có zone nào.</div>
			<?php else: ?>
				<?php foreach ($locations as $loc): ?>
                <?php
                    // Normalize keys safely
                    $zone_id = $loc['zone_id'] ?? $loc['_id'] ?? '';
                    $zone_name = $loc['name'] ?? $loc['zone_name'] ?? '';
                    $warehouse_name = $loc['warehouse']['name'] ?? ($loc['warehouse']['name'] ?? '');
                    $description = $loc['description'] ?? '';
                    $racks = $loc['racks'] ?? [];
                ?>
                <div class="zone" data-zone="<?=htmlspecialchars($zone_id)?>">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <h4><?=htmlspecialchars($zone_name)?> <small class="muted">(<?=htmlspecialchars($zone_id)?>)</small></h4>
                        <div>
                            <button class="btn small" onclick="deleteZone('<?=htmlspecialchars($zone_id)?>')">Xóa Zone</button>
                        </div>
                    </div>
                    <div class="muted"><?=htmlspecialchars($warehouse_name)?></div>
                    <p><?=htmlspecialchars($description)?></p>

                    <div class="rack-row">
                        <?php
                            // Map racks by rack_id to ensure correct placement (R1..R4) even if some are missing or out of order
                            $racksById = [];
                            if (is_array($racks)) {
                                foreach ($racks as $rr) {
                                    $rid0 = $rr['rack_id'] ?? null;
                                    if ($rid0) $racksById[$rid0] = $rr;
                                }
                            }
                        ?>
                        <?php for ($i=0;$i<4;$i++): ?>
                            <?php
                                $expectedId = 'R'.($i+1);
                                $r = $racksById[$expectedId] ?? null;
                                $rack_id = $expectedId;
                                $rack_name = $r['name'] ?? ('Rack '.($i+1));
                                $bins = $r['bins'] ?? [];
                            ?>
                            <div class="rack" data-rack="<?= htmlspecialchars($rack_id) ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <h5 style="margin:0;"><?= htmlspecialchars($rack_name) ?></h5>
                                    <div>
                                        <button class="btn small ghost" onclick="deleteRack('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">Xóa Rack</button>
                                    </div>
                                </div>
                                <?php if ($r): ?>
                                    <div class="muted">Bins: <?=count($bins)?></div>
                                    <div class="bins">
                                        <?php for ($b=0;$b<10;$b++): $bin = $bins[$b] ?? null; ?>
                                            <?php
                                                $bin_id = $bin['bin_id'] ?? '';
                                                $code = $bin['code'] ?? '';
                                                $bin_name = $bin['name'] ?? '';
                                                $quantity = $bin['quantity'] ?? 0;
                                                $status = $bin['status'] ?? 'empty';
                                                // Prefer displaying explicit name; else show code; else fallback to bin_id
                                                $bin_title = $bin_name !== '' ? $bin_name : ($code !== '' ? $code : ($bin_id !== '' ? $bin_id : 'Bin'));
                                            ?>
                                                            <div class="bin <?= $bin ? ($quantity > 0 ? 'full' : '') : 'empty' ?>" data-bin="<?= htmlspecialchars($bin_id) ?>">
                                                <?php if ($bin): ?>
                                                    <div><strong><?=htmlspecialchars($bin_title)?></strong></div>
                                                                    <div class="muted">Quantity: <?=htmlspecialchars($quantity)?></div>
                                                    <div class="muted"><?=htmlspecialchars($status)?></div>
                                                    <div style="margin-top:6px">
                                                        <?php $bin_numeric_id = isset($bin['id']) ? $bin['id'] : ''; ?>
                                                        <button class="btn small secondary" onclick="editBin('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>')">Edit</button>
                                                        <button class="btn small" style="background:#ef4444" onclick="deleteBin('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>','<?=htmlspecialchars((string)$bin_numeric_id)?>')">Del</button>
                                                    </div>
                                                        <?php else: ?>
                                                    <div class="muted">Trống</div>
                                                    <div style="margin-top:6px">
                                                        <button class="btn small" onclick="openAddBinModal('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">+ Bin</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="muted">Rack trống</div>
                                    <div style="margin-top:8px">
                                        <!-- Open modal flow for adding bin -->
                                        <button class="btn small" onclick="openAddBinModal('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">+ Thêm Bin</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Matrix editor: choose number of zones, then racks per zone, then bins per rack -->
	<div class="panel" style="margin-top:20px">
		<h3>Chỉnh sửa sơ đồ (Matrix) theo Zone → Rack → Bin</h3>
		<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
            <label>Số Zone (1-3):</label>
			<input id="numZones" type="number" value="1" min="1" max="3" style="width:80px">
			<button class="btn" id="applyZones">Tạo cấu hình Zone</button>
            <button class="btn" id="previewBtn">Xem trước</button>
            <!-- Warehouse inputs so the matrix can be attached to a specific warehouse (used when session warehouse isn't set) -->
            <!-- Warehouse is taken from session on the server; not shown here -->
            <button class="btn secondary" id="saveAllBtn">Lưu tất cả Zone</button>
            <button class="btn ghost" id="normalizeBtn" title="Chuẩn hóa tên các Rack">Chuẩn hóa tên Rack</button>
		</div>

		<!-- Zone configs generated above -->
		<div id="zoneConfigs" style="margin-bottom:12px"></div>
		<div id="matrixAreaPreview" style="margin-bottom:12px"></div>

		<!-- Grid generator removed per request (preview only) -->
	</div>

	<script>
		// Đổi sang đường dẫn đúng tới file process.php (relative với /view/page/manage/index.php)
		const API = 'locations/process.php';
		console.log('Location API ->', new URL(API, window.location.href).href);

		function post(data){
			return fetch(API, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
				.then(async r=>{
					const text = await r.text();
					try {
						return JSON.parse(text);
					} catch (err) {
						// Return an object so callers can show the raw HTML/text for debugging
						return { success: false, message: 'Server returned non-JSON response', status: r.status, raw: text };
					}
				})
				.catch(err=>({ success: false, message: 'Network error: ' + (err.message || err) }));
		}

		// Add Zone button removed; use matrix "Lưu tất cả Zone" to create zones.

		function addRackPrompt(zone_id, rack_id){
			const name = prompt('Tên rack:','Rack');
			if(!name) return;
            post({action:'add_rack',zone_id,rack_id,name}).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();});
		}

        function addBinPrompt(zone_id, rack_id){
            const bin_name = prompt('Tên bin (tùy chọn):', '');
            ensureRackThenAddBin(zone_id, rack_id, '', '', { bin_name });
        }

        // When user clicks an empty rack area, create the rack first (if needed) then add a first bin
        function addBinDirect(zone_id, rack_id){
            const bin_name = prompt('Tên bin (tùy chọn):', '');
            ensureRackThenAddBin(zone_id, rack_id, '', '', { bin_name });
        }

        // Helper: ensure rack exists (try manual model add first), then add bin.
        // opts can include {force:true, debug:true}
        function ensureRackThenAddBin(zone_id, rack_id, bin_id, code, opts){
            opts = opts || {};
            // First attempt: call the manual model endpoint to ensure rack is present
            let rackName = 'Rack ' + rack_id;
            const m = /^R(\d+)/i.exec(rack_id);
            if (m) { rackName = 'Rack ' + parseInt(m[1], 10); }
            const manualPayload = { action: 'add_rack_manual', zone_id, rack_id, name: rackName };
            if (opts.force) manualPayload.force = true;
            return post(manualPayload).then(manualRes => {
                // If manual succeeded, proceed to add bin
                const binPayload = { action: 'add_bin', zone_id, rack_id };
                if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                if (opts.force) binPayload.force = true;
                if (opts.debug) binPayload.debug = true;
                return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
            }).catch(() => {
                // If manual endpoint failed (network or server error), fall back to the original controller path
                const fallbackPayload = { action: 'add_rack', zone_id, rack_id, name: rack_id };
                if (opts.force) fallbackPayload.force = true;
                return post(fallbackPayload).then(rackRes => {
                    const binPayload = { action: 'add_bin', zone_id, rack_id };
                    if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                    if (opts.force) binPayload.force = true;
                    if (opts.debug) binPayload.debug = true;
                    return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
                }).catch(err => {
                    // If both attempts fail, still try add_bin directly as last resort
                    const binPayload = { action: 'add_bin', zone_id, rack_id };
                    if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                    if (opts.force) binPayload.force = true;
                    if (opts.debug) binPayload.debug = true;
                    return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
                });
            });
        }

        // Modal helpers
        function openAddBinModal(zone_id, rack_id){
            document.getElementById('modalZoneId').value = zone_id;
            document.getElementById('modalRackId').value = rack_id;
            const nm = document.getElementById('modalBinName'); if (nm) nm.value = '';
            document.getElementById('modalForce').checked = false;
            const m = document.getElementById('addBinModal'); m.setAttribute('aria-hidden','false');
        }
        function closeAddBinModal(){ document.getElementById('addBinModal').setAttribute('aria-hidden','true'); }
        function submitAddBinModal(e){
            e.preventDefault();
            const zone_id = document.getElementById('modalZoneId').value;
            const rack_id = document.getElementById('modalRackId').value;
            const bin_name = (document.getElementById('modalBinName')?.value || '').trim();
            const auto_rack = document.getElementById('modalAutoRack').checked;
            const force = document.getElementById('modalForce').checked;
            closeAddBinModal();
            if (auto_rack) {
                // gửi trực tiếp add_bin với auto_rack để server chọn rack kế tiếp
                post({ action: 'add_bin', zone_id, rack_id: 'auto', bin_name, auto_rack: true, force: !!force })
                    .then(res => { showToast(res); if (res.success) location.reload(); });
            } else {
                ensureRackThenAddBin(zone_id, rack_id, '', '', {force: !!force, debug:false, bin_name});
            }
            return false;
        }

        // Toast helper: accepts server response object or custom {success,message}
        function showToast(res){
            const obj = (res && typeof res === 'object') ? res : { success: false, message: String(res) };
            const div = document.createElement('div'); div.className = 'toast ' + (obj.success ? 'success' : 'error');
            div.textContent = (obj.message || (obj.success ? 'Thành công' : 'Lỗi'));
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(()=>{ div.style.opacity = '0'; setTimeout(()=>div.remove(),400); }, 3000);
        }

		function deleteZone(zone_id){ if(!confirm('Xóa zone?')) return; post({action:'delete_zone',zone_id}).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();}); }
        function deleteBin(zone_id,rack_id,bin_id,id){
            if(!confirm('Xóa bin?')) return;
            const payload = { action:'delete_bin' };
            if (id) { payload.id = id; }
            else { payload.zone_id = zone_id; payload.rack_id = rack_id; payload.bin_id = bin_id; }
            post(payload).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();});
        }
        function editBin(zone_id,rack_id,bin_id){
            const quantity = prompt('Quantity:');
            post({action:'update_bin',zone_id,rack_id,bin_id,binData:{quantity:parseInt(quantity)}}).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();});
        }

        function deleteRack(zone_id, rack_id){
            if(!confirm('Xóa rack này cùng toàn bộ bin bên trong?')) return;
            post({action:'delete_rack', zone_id, rack_id}).then(res=>{ alert(JSON.stringify(res)); if(res.success) location.reload(); });
        }

		// Matrix editor (grid generator removed) — preview-only mode retained below

		// --- New flow: create zone configs, preview and save all ---
		document.getElementById('applyZones').addEventListener('click', ()=>{
			const n = parseInt(document.getElementById('numZones').value)||1;
			if(n<1||n>3){alert('Số zone phải trong [1,3]');return}
			const container = document.getElementById('zoneConfigs');
			container.innerHTML = '';
			for(let i=0;i<n;i++){
				const div = document.createElement('div');
				div.style.border='1px solid #e5e7eb'; div.style.padding='8px'; div.style.marginBottom='8px';
				div.innerHTML = `
					<strong>Zone ${i+1}</strong>
					<label style="margin-left:12px">Zone ID:</label>
					<input type="text" class="zid" placeholder="Z${i+1}" value="Z${i+1}" style="width:120px">
					<label style="margin-left:12px">Zone name:</label>
					<input type="text" class="zname" placeholder="Zone ${i+1}" value="Zone ${i+1}" style="width:220px">
					<label style="margin-left:12px">Racks (1-4):</label>
					<input type="number" class="nracks" min="1" max="4" value="2" style="width:80px">
					<label style="margin-left:12px">Bins per rack (1-10):</label>
					<input type="number" class="nbins" min="1" max="10" value="8" style="width:80px">
				`;
				container.appendChild(div);
			}
		});

        document.getElementById('addZoneBtn').addEventListener('click', async ()=>{
            const zid = prompt('Zone ID (ví dụ Z4):'); if(!zid) return;
            const zname = prompt('Zone name:', 'Zone ' + zid) || zid;
            // create with empty racks
            const res = await post({ action: 'add_zone', zone_id: zid, name: zname, racks: [] });
            alert(JSON.stringify(res)); if(res.success) location.reload();
        });

		document.getElementById('previewBtn').addEventListener('click', ()=>{
			const configs = Array.from(document.querySelectorAll('#zoneConfigs > div'));
			if(configs.length===0){alert('Hãy tạo cấu hình zone trước');return}
			const preview = document.getElementById('matrixAreaPreview'); preview.innerHTML='';
			configs.forEach((cfg, zi)=>{
				const zid = cfg.querySelector('.zid').value.trim()||('Z'+(zi+1));
				const zname = cfg.querySelector('.zname').value.trim()||('Zone '+(zi+1));
				const nr = parseInt(cfg.querySelector('.nracks').value)||1;
				const nb = parseInt(cfg.querySelector('.nbins').value)||1;

				const zoneWrap = document.createElement('div');
				zoneWrap.className = 'preview-zone';
				const title = document.createElement('div'); title.innerHTML = `<strong>${zname} (${zid})</strong>`;
				zoneWrap.appendChild(title);

				for(let r=0; r<nr; r++){
					const rackDiv = document.createElement('div'); rackDiv.className='preview-rack';
					rackDiv.innerHTML = `<div class="rack-title">Rack ${r+1}</div>`;
					const cols = Math.max(1, Math.ceil(Math.sqrt(nb)));
					const binsGrid = document.createElement('div'); binsGrid.className='preview-bins';
					binsGrid.style.gridTemplateColumns = `repeat(${cols}, 36px)`;
					for(let b=0;b<nb;b++){
						const binDiv = document.createElement('div'); binDiv.className='preview-bin';
						binDiv.textContent = 'B'+(b+1);
						binsGrid.appendChild(binDiv);
					}
					rackDiv.appendChild(binsGrid);
					zoneWrap.appendChild(rackDiv);
				}

				preview.appendChild(zoneWrap);
			});
		});

        document.getElementById('saveAllBtn').addEventListener('click', async ()=>{
            const btn = document.getElementById('saveAllBtn');
            const originalText = btn.textContent;
            btn.disabled = true; btn.textContent = 'Đang lưu...';
            try {
                const configs = Array.from(document.querySelectorAll('#zoneConfigs > div'));
                if(configs.length===0){ alert('Hãy tạo cấu hình zone trước'); btn.disabled = false; btn.textContent = originalText; return; }

                // Build all zones in one payload and save once
                const zones = [];
                let totalRacks = 0; let totalBins = 0;
                configs.forEach((cfg, zi) => {
                    const zid = (cfg.querySelector('.zid')?.value || ('Z'+(zi+1))).trim();
                    const zname = (cfg.querySelector('.zname')?.value || ('Zone '+(zi+1))).trim();
                    let nr = parseInt(cfg.querySelector('.nracks')?.value || '1', 10);
                    let nb = parseInt(cfg.querySelector('.nbins')?.value || '1', 10);
                    if (!Number.isInteger(nr) || nr < 1) nr = 1; if (!Number.isInteger(nb) || nb < 1) nb = 1;
                    nr = Math.min(Math.max(nr,1), 4);
                    nb = Math.min(Math.max(nb,1), 10);

                    const racks = [];
                    for (let r = 0; r < nr; r++) {
                        const rack_id = 'R' + (r+1);
                        const bins = [];
                        for (let b = 0; b < nb; b++) {
                            const bin_id = 'B' + (b+1);
                            const code = `${zid}-R${r+1}-B${b+1}`;
                            bins.push({ bin_id, code, quantity: 0, status: 'empty', product: null });
                        }
                        racks.push({ rack_id, name: 'Rack ' + (r+1), bins });
                        totalBins += nb;
                    }
                    totalRacks += nr;
                    zones.push({ zone_id: zid, name: zname, description: '', racks });
                });

                showToast({ success: true, message: `Đang lưu ${zones.length} zone, ${totalRacks} rack, ${totalBins} bin...` });
                const res = await post({ action: 'save_location', zones });
                if (res && res.success) {
                    showToast({ success: true, message: `Lưu thành công ${zones.length} zone` });
                    setTimeout(()=>location.reload(), 600);
                } else {
                    const msg = res && res.message ? res.message : 'Không rõ lỗi';
                    showToast({ success: false, message: 'Lưu thất bại: ' + msg });
                    alert('Lưu thất bại: ' + msg);
                }
            } catch (err) {
                alert('Lỗi khi lưu: ' + (err.message || err));
            } finally {
                btn.disabled = false; btn.textContent = originalText;
            }
        });

        // Normalize rack names button
        document.getElementById('normalizeBtn').addEventListener('click', async ()=>{
            const res = await post({ action: 'normalize_rack_names' });
            showToast(res);
            if (res && res.success) setTimeout(()=>location.reload(), 500);
        });

		async function saveZone(zoneData) {
  try {
    const res = await fetch('./process.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'save_zone', zone: zoneData })
    });
    const text = await res.text();
    // Try parse JSON, if not JSON show full response for debugging
    try {
      const data = JSON.parse(text);
      console.log('response JSON:', data);
      return data;
    } catch (e) {
      console.error('Server returned non-JSON response:', text);
      return { success: false, message: 'Server returned non-JSON response', raw: text };
    }
  } catch (err) {
    console.error('Network error:', err);
    return { success:false, message: err.message };
  }
}

// Example call (thử lưu minimal payload)
document.addEventListener('DOMContentLoaded', () => {
  const sample = { _id: 'Z1', name: 'Zone A - test', warehouse: {id:'W1'}, description:'test', created_at: new Date().toISOString(), racks: [] };
  // uncomment to test automatically:
  // saveZone(sample).then(r=>alert(JSON.stringify(r)));
});
	</script>
</body>
</html>

