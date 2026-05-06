<?php
/**
 * Wartungs-Logbuch - PKV Design Edition
 * Fokus: Haus, Garten, Garage
 */
$jsonFile = 'data.json';

// 1. Daten laden
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// 2. Speichern-Logik (Neu & Bearbeiten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $date = $_POST['date'];
    $category = $_POST['category'];
    $task = $_POST['task'];
    $effort = (int)$_POST['effort'];

    if (!empty($_POST['old_date']) && isset($_POST['old_index'])) {
        $od = $_POST['old_date'];
        $oi = $_POST['old_index'];
        if (isset($data[$od][$oi])) {
            unset($data[$od][$oi]);
            if (empty($data[$od])) unset($data[$od]);
        }
    }

    if (!isset($data[$date])) {
        $data[$date] = [];
    }

    $data[$date][] = [
        'category' => $category,
        'task' => $task,
        'effort' => $effort,
        'timestamp' => time()
    ];
    
    ksort($data);
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 3. Lösch-Logik
if (isset($_GET['delete_date']) && isset($_GET['index'])) {
    $d = $_GET['delete_date'];
    $i = $_GET['index'];
    if (isset($data[$d][$i])) {
        unset($data[$d][$i]);
        if (empty($data[$d])) unset($data[$d]); 
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Vorbereitung für Heatmap (Letzte 365 Tage)
$chartLabels = [];
for ($i = 364; $i >= 0; $i--) {
    $d = new DateTime();
    $d->modify("-$i days");
    $chartLabels[] = $d->format('Y-m-d');
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartungslogbuch</title>
    <style>
        :root { 
            --pkv-blue: #007bff; 
            --pkv-green: #28a745;
            --bg-gray: #f0f2f5; 
            --dark-gray: #343a40;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-gray); 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            color: #1c1e21;
        }

        .container { 
            max-width: 1100px; 
            margin: 0 auto 30px auto; 
            padding: 0 20px; 
            flex: 1; 
            width: 100%; 
            box-sizing: border-box; 
        }

        .content-box { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
        }

        h2, h3 { color: #333; margin-top: 0; }
        
        form { 
            display: flex; 
            gap: 15px; 
            flex-wrap: wrap; 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 10px; 
            align-items: flex-end; 
            border: 1px solid #eee;
        }

        .form-group { display: flex; flex-direction: column; gap: 4px; }
        label { font-size: 0.75rem; font-weight: bold; color: #666; text-transform: uppercase; }
        
        input, select, button { padding: 10px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; }
        button { background: var(--pkv-blue); color: white; border: none; cursor: pointer; font-weight: bold; padding: 10px 20px; transition: background 0.2s; }
        button:hover { background: #0056b3; }

        /* Suchfeld Styling */
        .search-wrapper { margin-bottom: 15px; display: flex; gap: 10px; align-items: center; }
        .search-input { flex: 1; padding: 12px; border: 2px solid var(--pkv-blue); border-radius: 8px; font-size: 1rem; }

        /* Heatmap Styling */
        .heatmap-container { overflow-x: auto; padding: 10px 0; }
        .heatmap-wrapper { display: grid; grid-template-columns: repeat(53, 14px); grid-template-rows: repeat(7, 14px); gap: 3px; width: max-content; }
        .tile { width: 14px; height: 14px; border-radius: 2px; background: #ebedf0; position: relative; border: 1px solid rgba(0,0,0,0.05); cursor: pointer; }
        
        .level-1 { background-color: #cfe2ff; }
        .level-2 { background-color: #9ec5fe; }
        .level-3 { background-color: #6ea8fe; }
        .level-4 { background-color: #3d8bfd; }
        .level-5 { background-color: #0d6efd; }
        
        .tile:hover { transform: scale(1.2); z-index: 10; border-color: #666; }
        .tile:hover::after { content: attr(title); position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 4px 7px; font-size: 11px; border-radius: 3px; z-index: 100; white-space: nowrap; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 0.75rem; color: #666; border-bottom: 2px solid #eee; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; vertical-align: top; }
        tr.hidden { display: none; }
        
        .tag-cat { font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; background: #e9ecef; font-weight: bold; color: #495057; }
        .btn-delete { color: #dc3545; text-decoration: none; font-weight: bold; }
        .btn-edit { color: var(--pkv-blue); text-decoration: none; font-weight: bold; margin-right: 10px; }

        footer { background: var(--dark-gray); color: #bbb; padding: 30px; text-align: center; margin-top: 40px; font-size: 0.85rem; }
        footer a { color: white; text-decoration: none; border-bottom: 1px solid #555; }

        .main-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 30px; background-color: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header-logo img { height: 50px; width: auto; }
        .header-title-center h1 { margin: 0; font-size: 1.5rem; color: #333; }
        .header-nav-right .btn-dashboard { text-decoration: none; background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
    </style>
</head>
<body>

<header class="main-header">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <div class="header-logo"><img src="../logo.png" alt="Logo"></div>
    <div class="header-title-center"><h1>Wartungslogbuch</h1></div>
    <div class="header-nav-right"><a href="../index.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a></div>
</header>

<div class="container">

    <div class="content-box">
        <h2 id="form-title">Neue Tätigkeit protokollieren</h2>
        <form method="POST" id="logForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="old_date" id="old_date">
            <input type="hidden" name="old_index" id="old_index">
            
            <div class="form-group">
                <label>Datum</label>
                <input type="date" name="date" id="input_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Bereich</label>
                <select name="category" id="input_category">
                    <option value="Haus">🏠 Haus</option>
                    <option value="Garten">🌻 Garten</option>
                    <option value="Auto">🚗 Auto</option>
                    <option value="Garage">🔧 Garage</option>
                    <option value="Technik">💻 Technik</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label>Tätigkeit</label>
                <input type="text" name="task" id="input_task" placeholder="Was wurde erledigt?" required>
            </div>
            <div class="form-group">
                <label>Aufwand</label>
                <select name="effort" id="input_effort">
                    <option value="1">1 (Sehr klein)</option>
                    <option value="2">2</option>
                    <option value="3" selected>3</option>
                    <option value="4">4</option>
                    <option value="5">5 (Großprojekt)</option>
                </select>
            </div>
            <button type="submit" id="submit-btn">Speichern</button>
            <button type="button" id="cancel-btn" style="display:none; background:#6c757d;" onclick="resetForm()">Abbrechen</button>
        </form>
    </div>

    <div class="content-box">
        <h2>Aktivitätsübersicht</h2>
        <p style="font-size: 0.8rem; color: #666; margin-bottom: 10px;">💡 Klicke auf eine Kachel, um die Historie nach diesem Tag zu filtern.</p>
        <div class="heatmap-container">
            <div class="heatmap-wrapper">
                <?php
                foreach ($chartLabels as $dateKey) {
                    $dayEffort = 0;
                    $tasksCount = 0;
                    if (isset($data[$dateKey])) {
                        foreach ($data[$dateKey] as $entry) {
                            $dayEffort = max($dayEffort, $entry['effort']);
                            $tasksCount++;
                        }
                    }
                    $class = ($dayEffort > 0) ? "level-$dayEffort" : "";
                    $formattedDate = date("d.m.Y", strtotime($dateKey));
                    $title = $formattedDate . ($tasksCount > 0 ? " ($tasksCount Einträge)" : " (Keine Aktivität)");
                    
                    // Click Event hinzugefügt
                    echo "<div class='tile $class' title='$title' onclick=\"filterByDate('$formattedDate')\"></div>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="content-box">
        <h2>Historie</h2>
        
        <div class="search-wrapper">
            <input type="text" id="tableSearch" class="search-input" placeholder="Nach Tätigkeit, Bereich oder Datum (z.B. 01.05.2024) suchen..." onkeyup="filterTable()">
            <button onclick="clearSearch()" style="background:#6c757d; padding: 12px;">Reset</button>
        </div>

        <table id="historyTable">
            <thead>
                <tr>
                    <th style="width: 120px;">Datum</th>
                    <th style="width: 100px;">Bereich</th>
                    <th>Tätigkeit</th>
                    <th style="width: 100px;">Aufwand</th>
                    <th style="width: 80px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $reversedData = array_reverse($data, true);
                foreach ($reversedData as $date => $entries): 
                    foreach (array_reverse($entries, true) as $index => $info): ?>
                    <tr>
                        <td class="col-date"><?= date("d.m.Y", strtotime($date)) ?></td>
                        <td class="col-cat"><span class="tag-cat"><?= htmlspecialchars($info['category']) ?></span></td>
                        <td class="col-task"><?= htmlspecialchars($info['task']) ?></td>
                        <td style="color: #666; font-size: 0.85rem;">Stufe <?= htmlspecialchars($info['effort']) ?></td>
                        <td>
                            <a href="javascript:void(0)" class="btn-edit" onclick="editEntry('<?= $date ?>', '<?= $index ?>', '<?= htmlspecialchars($info['category']) ?>', '<?= htmlspecialchars(addslashes($info['task'])) ?>', '<?= $info['effort'] ?>')">✏️</a>
                            <a href="?delete_date=<?= $date ?>&index=<?= $index ?>" class="btn-delete" onclick="return confirm('Eintrag löschen?')">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p><strong>Wartungslogbuch</strong> | Lizenziert unter <a href="https://www.gnu.org/licenses/agpl-3.0.de.html" target="_blank">AGPL-3.0</a> | Source: <a href="https://github.com/herr-nm/Privat_Wartungslogbuch" target="_blank">GitHub</a></p>
</footer>

<script>
// --- SUCHFUNKTION ---
function filterTable() {
    const input = document.getElementById("tableSearch");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("historyTable");
    const tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let textContent = tr[i].textContent.toLowerCase();
        if (textContent.indexOf(filter) > -1) {
            tr[i].classList.remove("hidden");
        } else {
            tr[i].classList.add("hidden");
        }
    }
}

// --- HEATMAP FILTER ---
function filterByDate(dateString) {
    const searchInput = document.getElementById("tableSearch");
    searchInput.value = dateString;
    filterTable();
    // Scroll zur Tabelle
    document.getElementById("historyTable").scrollIntoView({ behavior: 'smooth' });
}

function clearSearch() {
    document.getElementById("tableSearch").value = "";
    filterTable();
}

// --- BEARBEITEN FUNKTION ---
function editEntry(date, index, category, task, effort) {
    document.getElementById('input_date').value = date;
    document.getElementById('input_category').value = category;
    document.getElementById('input_task').value = task;
    document.getElementById('input_effort').value = effort;
    
    document.getElementById('old_date').value = date;
    document.getElementById('old_index').value = index;
    
    document.getElementById('form-title').innerText = "Eintrag bearbeiten";
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.innerHTML = "Änderungen speichern";
    submitBtn.style.background = "var(--pkv-green)";
    document.getElementById('cancel-btn').style.display = "inline-block";
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('logForm').reset();
    document.getElementById('old_date').value = "";
    document.getElementById('old_index').value = "";
    document.getElementById('form-title').innerText = "Neue Tätigkeit protokollieren";
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.innerHTML = "Speichern";
    submitBtn.style.background = "var(--pkv-blue)";
    document.getElementById('cancel-btn').style.display = "none";
    document.getElementById('input_date').value = '<?= date('Y-m-d') ?>';
}
</script>

</body>
</html>