<?php
declare(strict_types=1);

/**
 * Proyecto: Árbol Genealógico Avanzado con vista HTML simple
 * Participante: Juan Abad Y.
 * Requiere PHP 8.1+
 * Persistencia: family.json en el mismo directorio
 *
 * Instrucciones:
 * - Copiar este archivo como index.php y ejecútalo en el servidor local.
 * - La primera carga crea el árbol con la raíz virtual (ID=0).
 * - Se puede agregar personas, mover subárboles, eliminarlos, listar DFS/BFS, etc.
 */

/* ================== MODELO (igual al anterior) ================== */
final class PersonNode
{
    public function __construct(
        public readonly int|string $id,
        public string $name,
        public int|string|null $parentId = null,
        /** @var array<int|string> */
        public array $childrenIds = []
    ) {}

    public function isRootLike(): bool
    {
        return $this->parentId === null || $this->parentId === 0;
    }
}

final class FamilyTree
{
    /** @var array<int|string, PersonNode> */
    private array $nodes = [];

    public const VIRTUAL_ROOT_ID = 0;

    public function __construct(string $virtualRootName = 'ROOT')
    {
        $this->nodes[self::VIRTUAL_ROOT_ID] = new PersonNode(self::VIRTUAL_ROOT_ID, $virtualRootName, null, []);
    }

    public function addPerson(int|string $id, string $name, int|string $parentId = self::VIRTUAL_ROOT_ID): void
    {
        if (isset($this->nodes[$id])) {
            throw new InvalidArgumentException("Ya existe una persona con id '$id'.");
        }
        if (!isset($this->nodes[$parentId])) {
            throw new InvalidArgumentException("No existe el padre con id '$parentId'.");
        }
        $this->nodes[$id] = new PersonNode($id, $name, null, []);
        $this->attach($parentId, $id);
    }

    public function rename(int|string $id, string $newName): void
    {
        $node = $this->getNodeOrFail($id);
        $node->name = $newName;
    }

    public function attach(int|string $parentId, int|string $childId): void
    {
        $parent = $this->getNodeOrFail($parentId);
        $child  = $this->getNodeOrFail($childId);

        if ($parentId === $childId) {
            throw new LogicException("Un nodo no puede ser padre de sí mismo.");
        }
        if ($this->isDescendant($childId, $parentId)) {
            throw new LogicException("Ciclo detectado: intentar colgar al ancestro '$parentId' como hijo de su descendiente '$childId'.");
        }

        if ($child->parentId !== null) {
            $this->detach($child->parentId, $childId);
        }

        $child->parentId   = $parentId;
        $parent->childrenIds[] = $childId;
    }

    public function moveSubtree(int|string $subtreeRootId, int|string $newParentId): void
    {
        $this->attach($newParentId, $subtreeRootId);
    }

    public function deleteSubtree(int|string $id): void
    {
        if ($id === self::VIRTUAL_ROOT_ID) {
            throw new LogicException("No se puede eliminar la raíz virtual.");
        }
        $node = $this->getNodeOrFail($id);

        if ($node->parentId !== null) {
            $this->detach($node->parentId, $id);
        }

        $queue = [$id];
        while ($queue) {
            $currId = array_shift($queue);
            $curr = $this->nodes[$currId] ?? null;
            if (!$curr) continue;
            foreach ($curr->childrenIds as $childId) $queue[] = $childId;
            unset($this->nodes[$currId]);
        }
    }

    public function dfs(int|string $startId = self::VIRTUAL_ROOT_ID): array
    {
        $this->getNodeOrFail($startId);
        $result = [];
        $stack = [$startId];
        while ($stack) {
            $id = array_pop($stack);
            $result[] = $id;
            $children = $this->nodes[$id]->childrenIds;
            for ($i = count($children) - 1; $i >= 0; $i--) $stack[] = $children[$i];
        }
        return $result;
    }

    public function bfs(int|string $startId = self::VIRTUAL_ROOT_ID): array
    {
        $this->getNodeOrFail($startId);
        $result = [];
        $queue = [$startId];
        while ($queue) {
            $id = array_shift($queue);
            $result[] = $id;
            foreach ($this->nodes[$id]->childrenIds as $childId) $queue[] = $childId;
        }
        return $result;
    }

    public function findById(int|string $id): ?PersonNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function maxDepth(int|string $startId = self::VIRTUAL_ROOT_ID): int
    {
        $this->getNodeOrFail($startId);
        $maxDepth = 0;
        $stack = [[$startId, 1]];
        while ($stack) {
            [$id, $depth] = array_pop($stack);
            $maxDepth = max($maxDepth, $depth);
            foreach ($this->nodes[$id]->childrenIds as $childId) $stack[] = [$childId, $depth + 1];
        }
        return $maxDepth ?: 1;
    }

    public function countDescendants(int|string $id): int
    {
        $this->getNodeOrFail($id);
        $count = 0;
        $queue = [...$this->nodes[$id]->childrenIds];
        while ($queue) {
            $cid = array_shift($queue);
            $count++;
            foreach ($this->nodes[$cid]->childrenIds as $gcid) $queue[] = $gcid;
        }
        return $count;
    }

    public function allNodes(): array
    {
        return $this->nodes;
    }

    public function toJson(): string
    {
        $out = [];
        foreach ($this->nodes as $id => $n) {
            $out[] = [
                'id' => $id,
                'name' => $n->name,
                'parentId' => $n->parentId,
                'children' => $n->childrenIds,
            ];
        }
        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $tree = new self();
        $tree->nodes = [];
        foreach ($data as $row) {
            $tree->nodes[$row['id']] = new PersonNode(
                $row['id'], $row['name'], $row['parentId'], $row['children']
            );
        }
        if (!isset($tree->nodes[self::VIRTUAL_ROOT_ID])) {
            $tree->nodes[self::VIRTUAL_ROOT_ID] = new PersonNode(self::VIRTUAL_ROOT_ID, 'ROOT', null, []);
        }
        return $tree;
    }

    private function getNodeOrFail(int|string $id): PersonNode
    {
        $node = $this->nodes[$id] ?? null;
        if ($node === null) throw new InvalidArgumentException("No existe el nodo con id '$id'.");
        return $node;
    }

    private function detach(int|string $parentId, int|string $childId): void
    {
        $parent = $this->getNodeOrFail($parentId);
        $child  = $this->getNodeOrFail($childId);

        $idx = array_search($childId, $parent->childrenIds, true);
        if ($idx !== false) array_splice($parent->childrenIds, $idx, 1);
        if ($child->parentId === $parentId) $child->parentId = null;
    }

    private function isDescendant(int|string $aId, int|string $bId): bool
    {
        $this->getNodeOrFail($aId);
        $this->getNodeOrFail($bId);
        $queue = [$bId];
        while ($queue) {
            $curr = array_shift($queue);
            if ($curr === $aId) return true;
            foreach ($this->nodes[$curr]->childrenIds as $c) $queue[] = $c;
        }
        return false;
    }
}

/* ================== PERSISTENCIA ================== */
const DATA_FILE = __DIR__ . '/family.json';

function loadTree(): FamilyTree {
    if (!file_exists(DATA_FILE)) {
        return new FamilyTree('ROOT');
    }
    $json = file_get_contents(DATA_FILE) ?: '[]';
    try {
        return FamilyTree::fromJson($json);
    } catch (Throwable) {
        return new FamilyTree('ROOT');
    }
}
function saveTree(FamilyTree $tree): void {
    file_put_contents(DATA_FILE, $tree->toJson());
}

/* ================== CONTROLADOR SIMPLE ================== */
$tree = loadTree();
$msg  = null; $err = null;

$action = $_POST['action'] ?? null;
try {
    if ($action === 'add') {
        $id  = trim((string)($_POST['id'] ?? ''));
        $nm  = trim((string)($_POST['name'] ?? ''));
        $pid = (string)($_POST['parent'] ?? '0');
        if ($id === '' || $nm === '') throw new InvalidArgumentException("ID y Nombre son obligatorios.");
        $tree->addPerson($id, $nm, $pid);
        $msg = "Persona '$nm' (ID=$id) agregada bajo padre $pid.";
    } elseif ($action === 'rename') {
        $id = (string)($_POST['id'] ?? '');
        $nm = trim((string)($_POST['name'] ?? ''));
        if ($id === '' || $nm === '') throw new InvalidArgumentException("ID y nuevo nombre son obligatorios.");
        $tree->rename($id, $nm);
        $msg = "Persona ID=$id renombrada a '$nm'.";
    } elseif ($action === 'attach') {
        $pid = (string)($_POST['parent'] ?? '');
        $cid = (string)($_POST['child'] ?? '');
        if ($pid === '' || $cid === '') throw new InvalidArgumentException("Padre y Hijo son obligatorios.");
        $tree->attach($pid, $cid);
        $msg = "Adjuntado hijo $cid a padre $pid.";
    } elseif ($action === 'move') {
        $cid = (string)($_POST['child'] ?? '');
        $pid = (string)($_POST['newparent'] ?? '');
        if ($pid === '' || $cid === '') throw new InvalidArgumentException("Nuevo padre y subárbol son obligatorios.");
        $tree->moveSubtree($cid, $pid);
        $msg = "Movido subárbol $cid debajo de $pid.";
    } elseif ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        if ($id === '') throw new InvalidArgumentException("ID es obligatorio.");
        $tree->deleteSubtree($id);
        $msg = "Eliminado subárbol con raíz $id.";
    } elseif ($action === 'reset') {
        $tree = new FamilyTree('ROOT');
        $msg = "Árbol reiniciado.";
    }
    saveTree($tree);
} catch (Throwable $e) {
    $err = $e->getMessage();
}

/* ============== HELPERS DE VISTA ============== */
function optionsForIds(FamilyTree $tree): string {
    $opts = '';
    foreach ($tree->allNodes() as $id => $n) {
        $label = $id . ' — ' . $n->name;
        $opts .= '<option value="'.htmlspecialchars((string)$id).'">'.htmlspecialchars($label).'</option>';
    }
    return $opts;
}
function renderTreeHtml(FamilyTree $tree, int|string $id = FamilyTree::VIRTUAL_ROOT_ID): string {
    $node = $tree->findById($id);
    if (!$node) return '';
    $html = '<li><strong>'.htmlspecialchars((string)$node->id).'</strong> — '.htmlspecialchars($node->name).'</li>';
    if ($node->childrenIds) {
        $html .= '<ul>';
        foreach ($node->childrenIds as $cid) {
            $html .= renderTreeHtml($tree, $cid);
        }
        $html .= '</ul>';
    }
    return $html;
}
function traverseToLabels(FamilyTree $tree, array $ids): string {
    $labels = [];
    foreach ($ids as $id) {
        $n = $tree->findById($id);
        if ($n) $labels[] = $id.'('.$n->name.')';
    }
    return implode(' → ', $labels);
}

/* ============== CONSULTAS RÁPIDAS ============== */
$dfsOrder = traverseToLabels($tree, $tree->dfs());
$bfsOrder = traverseToLabels($tree, $tree->bfs());
$maxDepth = $tree->maxDepth(FamilyTree::VIRTUAL_ROOT_ID);
$rootDesc = $tree->countDescendants(FamilyTree::VIRTUAL_ROOT_ID);

/* ================== VISTA HTML ================== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Árbol Genealógico Avanzado — Demo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { color-scheme: light dark; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
  h1 { margin: 0 0 8px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
  .card { border: 1px solid #ddd; border-radius: 12px; padding: 16px; background: #fff; }
  .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
  .ok { background: #e8f5e9; border: 1px solid #a5d6a7; }
  .err { background: #ffebee; border: 1px solid #ef9a9a; }
  form { display: grid; gap: 8px; }
  label { font-weight: 600; }
  input[type=text], select { padding: 8px; border-radius: 8px; border: 1px solid #ccc; }
  button { padding: 8px 12px; border-radius: 10px; border: 1px solid #333; background: #111; color: #fff; cursor: pointer; }
  button.secondary { background: #444; }
  ul { list-style: none; padding-left: 18px; }
  ul ul { border-left: 2px dashed #ccc; margin-left: 8px; padding-left: 12px; }
  .small { color: #555; font-size: 12px; }
  .muted { color: #777; }
  .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  code { background: #f6f8fa; padding: 2px 6px; border-radius: 6px; }
</style>
</head>
<body>
  <h1>Árbol Genealógico Avanzado <span class="small muted">Reto TI</span></h1>

  <?php if ($msg): ?><div class="alert ok">✅ <?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err">⚠️ <?=htmlspecialchars($err)?></div><?php endif; ?>

  <div class="grid">
    <div class="card">
      <h2>Estado actual</h2>
      <p class="small">Raíz virtual: <code>ID=0</code> (permite múltiples raíces reales).</p>
      <ul><?= renderTreeHtml($tree) ?></ul>
      <p class="small muted">Total nodos (incluye raíz virtual): <?= count($tree->allNodes()) ?></p>
    </div>

    <div class="card">
      <h2>Agregar persona</h2>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <label>ID (texto o número)</label>
        <input type="text" name="id" placeholder="Ej: 1">
        <label>Nombre</label>
        <input type="text" name="name" placeholder="Ej: Abuela">
        <label>Padre</label>
        <select name="parent"><?= optionsForIds($tree) ?></select>
        <div class="row">
          <button>Agregar</button>
          <button class="secondary" type="submit" formaction="" formmethod="post" name="action" value="reset"
            onclick="return confirm('¿Reiniciar el árbol? Se perderán los datos.');">Reiniciar árbol</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Renombrar</h2>
      <form method="post">
        <input type="hidden" name="action" value="rename">
        <label>Persona</label>
        <select name="id"><?= optionsForIds($tree) ?></select>
        <label>Nuevo nombre</label>
        <input type="text" name="name" placeholder="Ej: Mamá">
        <button>Renombrar</button>
      </form>
    </div>

    <div class="card">
      <h2>Adjuntar (padre → hijo)</h2>
      <form method="post">
        <input type="hidden" name="action" value="attach">
        <label>Padre</label>
        <select name="parent"><?= optionsForIds($tree) ?></select>
        <label>Hijo</label>
        <select name="child"><?= optionsForIds($tree) ?></select>
        <button>Adjuntar</button>
      </form>
      <hr>
      <h2>Mover subárbol</h2>
      <form method="post">
        <input type="hidden" name="action" value="move">
        <label>Subárbol (raíz a mover)</label>
        <select name="child"><?= optionsForIds($tree) ?></select>
        <label>Nuevo padre</label>
        <select name="newparent"><?= optionsForIds($tree) ?></select>
        <button>Mover</button>
      </form>
    </div>

    <div class="card">
      <h2>Eliminar subárbol</h2>
      <form method="post" onsubmit="return confirm('¿Eliminar este subárbol COMPLETO?');">
        <input type="hidden" name="action" value="delete">
        <label>Persona</label>
        <select name="id"><?= optionsForIds($tree) ?></select>
        <button>Eliminar</button>
      </form>
      <p class="small muted">No se puede eliminar la raíz virtual (ID=0).</p>
    </div>

    <div class="card">
      <h2>Recorridos</h2>
      <p><strong>DFS (preorden):</strong><br><?= htmlspecialchars($dfsOrder) ?></p>
      <p><strong>BFS (por niveles):</strong><br><?= htmlspecialchars($bfsOrder) ?></p>
      <h3>Estadísticas</h3>
      <p>Profundidad máxima (desde ROOT): <strong><?= $maxDepth ?></strong></p>
      <p>Descendientes totales de ROOT: <strong><?= $rootDesc ?></strong></p>
      <details>
        <summary>Exportar JSON</summary>
        <pre><code><?= htmlspecialchars($tree->toJson()) ?></code></pre>
      </details>
    </div>

    <div class="card">
      <h2>Buscar por ID</h2>
      <form onsubmit="event.preventDefault(); const id=this.querySelector('input').value.trim(); if(!id) return; const el=[...document.querySelectorAll('option')].find(o=>o.value===id); alert(el?('ID '+id+': '+el.textContent):'No encontrado');">
        <label>ID</label>
        <input type="text" placeholder="Ej: 2">
        <button>Buscar</button>
      </form>
      <p class="small muted">Para métricas específicas puedes leer el JSON y/o adaptar la vista.</p>
    </div>
  </div>

  <p class="small muted">Tip: crea algunos nodos (Abuela→Mamá→Yo) y prueba mover “Yo” a otro padre para ver los recorridos cambiar.</p>
</body>
</html>
