<?php
// Configurações para permitir o acesso a partir de qualquer origem (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers:{$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

// Inclusão do arquivo 'funcoes.php' que deve conter as funções auxiliares
include 'funcoes.php';

// Estabelecendo conexão com o banco de dados usando a função 'getconexaonew' de 'funcoes.php'
$condb = getconexaonew(dbclientenew($_POST['idclientesac'], 31));

// Verifica qual operação (op) foi solicitada via POST
switch ($_POST['op']) {
    case "qtdregistros":
        // Caso a operação seja 'qtdregistros', realiza a contagem de registros na tabela 'tbbairros'
        $sql = "SELECT COUNT(*) AS qtdregistros FROM tbdepartamentos WHERE id > 0" . $_POST['filtro'];
        $stmt = $condb->prepare($sql);

        if (!$stmt->execute()) {
            // Se ocorrer um erro na execução, define a quantidade de registros como 0
            $jsdqtdregistros['qtdregistros'] = 0;
        } else {
            // Caso contrário, obtém o resultado da contagem e retorna como resposta no formato JSON
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $jsdqtdregistros['qtdregistros'] = $resultado[0]['qtdregistros'];
        }
        echo json_encode($jsdqtdregistros);
        break;

    case "listagem":
        // Caso a operação seja 'listagem', realiza a busca dos registros na tabela 'tbdepartamentos'
        $sql = "SELECT id, descricao FROM tbdepartamentos";
        $stmt = $condb->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($resultado) === 0) {
            // Se não houver registros, retorna uma lista vazia como resposta no formato JSON
            $JsondataLista = [];
        } else {
            // Caso contrário, monta uma lista de registros e retorna como resposta no formato JSON
            $JsondataLista = $resultado;
        }
        echo json_encode($JsondataLista);
        break;

    case "rotinacrud":
        // Caso a operação seja 'rotinacrud', realiza operações de criação, atualização ou exclusão de registros na tabela 'tbbairros'
        $registro = json_decode($_POST['registro']);
        $opcrud = $_POST['opcrud'];
        $sql = "";
        $idv = 0;
        $strerro = "";
        $verro = 0;
        $codop = $_POST['codop'];
        $txtatividade = "";

        switch ((int)$opcrud) {
            case 1:
                // Se opcrud for 1, significa que é uma operação de inclusão
                $txtatividade = "Incluiu o Bairro: ";

                // Define a query SQL de inserção
                $sql = "INSERT INTO tbdepartamentos (descricao) VALUES (:descricao)";
                break;

            case 2:
                // Se opcrud for 2, significa que é uma operação de atualização
                $txtatividade = "Alterou o Bairro: " . $registro->id;

                // Define a query SQL de atualização
                $sql = "UPDATE tbdepartamentos SET descricao=:descricao WHERE id=:id";
                break;

            case 3:
                // Se opcrud for 3, significa que é uma operação de exclusão
                $txtatividade = "Excluiu o Bairro: " . $registro->id;

                // Define a query SQL de exclusão
                $sql = "DELETE FROM tbdepartamentos WHERE id=:id";
                break;
        }

        // Desabilita o modo de autocommit para iniciar a transação manualmente
        $condb->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $condb->beginTransaction();

        // Prepara a query SQL e executa, vinculando os parâmetros a serem inseridos, atualizados ou excluídos
        $stmt = $condb->prepare($sql);
        if ((int)$opcrud < 3) {
            $stmt->bindValue(':descricao', $registro->descricao);
        }
        if ((int)$opcrud >= 2) {
            $stmt->bindValue(':id', $registro->id);
        }
        $ret = $stmt->execute();
        
        if (!$ret) {
            // Se ocorrer algum erro na execução, registra o erro e incrementa o contador de erros
            $strerro = $stmt->errorInfo()[2] . "<br>";
            ++$verro;
        }

        // Caso for inclusão, pega o último id gerado, senão, pega o id do usuário já cadastrado
        $idv = (int)$opcrud >= 2 ? $registro->id : $condb->lastInsertId();

        if ((int)$opcrud === 1) {
            // Se for inclusão, atualiza a mensagem de atividade
            $txtatividade = $txtatividade . $idv;
        }

        // Registra a atividade do usuário em uma função auxiliar 'registraratividadeusuariofunction'
        $retornoatv = registraratividadeusuariofunction($condb, $_POST['idfilial'], $_POST['idusuario'], $codop, $txtatividade, json_encode($registro));

        if (!is_numeric($retornoatv)) {
            // Se ocorrer algum erro ao registrar a atividade, registra o erro e incrementa o contador de erros
            $strerro = $strerro . "<br>" . $retornoatv;
            ++$verro;
        }

        if ($verro == 0) {
            // Se não houver erros, faz o commit da transação e retorna o id do registro
            $condb->commit();
            echo $idv;
        } else {
            // Caso contrário, faz o rollback da transação e retorna a mensagem de erro
            $condb->rollback();
            echo  "E - Erro(s) ao processar o registro: " . $strerro;
        }
        break;
}
?>
