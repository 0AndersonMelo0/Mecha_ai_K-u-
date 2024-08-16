<?php
require_once '../dependencias/config.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$cursoFilter = isset($_GET['curso']) ? trim($_GET['curso']) : '';
$serieFilter = isset($_GET['serie']) ? trim($_GET['serie']) : '';

try {
    // Consulta base com junção das tabelas aluno e emprestimos pela matrícula
    $query = "
        SELECT
            a.nome_completo AS aluno_nome,
            e.matricula AS aluno_matricula,
            e.titulo_livro,
            e.numero_registro,
            a.curso,
            a.serie,
            CASE
                WHEN e.data_rascunho IS NOT NULL THEN DATEDIFF(NOW(), e.data_rascunho)
                ELSE DATEDIFF(NOW(), e.data_emprestimo)
            END AS dias,
            CASE
                WHEN e.data_rascunho IS NOT NULL THEN DATE_FORMAT(e.data_rascunho + INTERVAL 7 DAY, '%d/%m')
                ELSE DATE_FORMAT(e.data_emprestimo + INTERVAL 7 DAY, '%d/%m')
            END AS prazo,
            e.status
        FROM emprestimos e
        JOIN aluno a ON e.matricula = a.matricula  -- Junção pela matrícula
        WHERE e.data_devolucao IS NULL
    ";

    // Adiciona filtros de curso e série se fornecidos
    $conditions = [];
    if ($search) {
        $conditions[] = "(a.nome_completo LIKE :search OR e.matricula LIKE :search)";
    }
    if ($cursoFilter && $cursoFilter != 'TODOS') {
        $conditions[] = "a.curso = :curso";
    }
    if ($serieFilter && $serieFilter != 'TODOS') {
        $conditions[] = "a.serie = :serie";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $stmt = $conn->prepare($query);

    // Vincula os parâmetros de busca
    if ($search) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    if ($cursoFilter && $cursoFilter != 'TODOS') {
        $stmt->bindValue(':curso', $cursoFilter);
    }
    if ($serieFilter && $serieFilter != 'TODOS') {
        $stmt->bindValue(':serie', $serieFilter);
    }

    $stmt->execute();
    $emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
        header('Content-Type: application/json');
        echo json_encode($emprestimos);
        exit;
    }
} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca</title>
    <link rel="stylesheet" href="Css/style.css">
    <link rel="stylesheet" href="Css/indextb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
</head>
<body>

    <div class="container">
        <div class="cabecario">
            <h1 class="title">EMPRÉSTIMOS</h1>
            <div class="search-container">
                <input type="text" id="search-box" class="search-box" placeholder="Pesquisar aluno...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <div class="filtros">
                <div>
                    <select id="turma-filter" class="turma-filter" name="turma-filter">
                        <option value="">SERIE</option>
                        <option value="1">1º ANO</option>
                        <option value="2">2º ANO</option>
                        <option value="3">3º ANO</option>
                        <option value="TODOS">TODOS</option>
                    </select>
                </div>
                <div>
                    <select id="curso-filter" class="curso-filter" name="curso-filter">
                        <option value="">CURSO</option>
                        <option value="Enfermagem">ENFERMAGEM</option>
                        <option value="Informática">INFORMÁTICA</option>
                        <option value="Comércio">COMÉRCIO</option>
                        <option value="Administração">ADMINISTRAÇÃO</option>
                        <option value="TODOS">TODOS</option>
                    </select>
                </div>
            </div>
            <button class="print-button"><i class="fa-solid fa-print"></i> RELATÓRIO</button>
        </div>
        <table id="booksTable">
            <thead>
                <tr>
                    <th>NOME DO ALUNO</th>
                    <th>MATRÍCULA</th>
                    <th>NOME DO LIVRO</th>
                    <th>DIAS</th>
                    <th>PRAZO</th>
                    <th>MAIS INFORMAÇÕES</th>
                    <th>RENOVAR</th>
                    <th>APAGAR</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <!-- Os dados da tabela serão preenchidos pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const searchBox = document.getElementById('search-box');
            const tableBody = document.getElementById('table-body');
            const turmaFilter = document.getElementById('turma-filter');
            const cursoFilter = document.getElementById('curso-filter');

            function fetchData() {
                const query = searchBox.value;
                const serie = turmaFilter.value;
                const curso = cursoFilter.value;

                fetch(`index.php?ajax=true&serie=${encodeURIComponent(serie)}&curso=${encodeURIComponent(curso)}&search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        updateTable(data);
                    })
                    .catch(error => console.error('Error:', error));
            }

            function updateTable(emprestimos) {
                tableBody.innerHTML = '';

                if (emprestimos.length > 0) {
                    emprestimos.forEach(emprestimo => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="aluno-nome">${emprestimo.aluno_nome}</td>
                            <td class="aluno-matricula">${emprestimo.aluno_matricula}</td>
                            <td>${emprestimo.titulo_livro}</td>
                            <td class="days">${emprestimo.dias}</td>
                            <td>${emprestimo.prazo}</td>
                            <td>
                                <a href="info/index.php?matricula=${encodeURIComponent(emprestimo.aluno_matricula)}&id=${encodeURIComponent(emprestimo.id)}" class="icon-button">
                                    <i class="fa-solid fa-file-lines"></i>
                                </a>
                            </td>
                            <td>
                                <a href="renew.php?id=${encodeURIComponent(emprestimo.id)}" class="icon-button">
                                    <i class="fa-regular fa-calendar"></i>
                                </a>
                            </td>
                            <td>
                                <a href="delete.php?id=${encodeURIComponent(emprestimo.id)}" class="icon-button">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                } else {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="8">Nenhum registro encontrado.</td>';
                    tableBody.appendChild(row);
                }
            }

            searchBox.addEventListener('input', fetchData);
            turmaFilter.addEventListener('change', fetchData);
            cursoFilter.addEventListener('change', fetchData);

            fetchData(); // Carrega os dados iniciais
        });
    </script>
</body>
</html>
