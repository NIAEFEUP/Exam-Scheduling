<?php
/*
Exam Scheduler By NIFEUP
@Author: Diogo Basto (ei09082@fe.up.pt)
*/

//params 
//pv_curso_id 742 -> MIEIC
//pv_periodos 2 ->1º semestre 3->2ºsemestre
//pv_ano_lectivo 2012

//username e password do sifeup
	$username=$_POST['username'];
	$password=$_POST['password'];
	
//tratar parametros da pagina
	$anoletivo=$_POST['anolectivo'];
	switch($_POST['curso'])
	{
		case 'MIEIC': $curso_id='742';break;
		case 'CINF': $curso_id='454';break;
		case 'LCEEMG': $curso_id='738';break;
		case 'MEMG': $curso_id='739';break;
		case 'MIB': $curso_id='728';break;
		case 'MIEC': $curso_id='740';break;
		case 'MIEA': $curso_id='726';break;
		case 'MIEEC': $curso_id='741';break;
		case 'MIEIG': $curso_id='725';break;
		case 'MIEM': $curso_id='743';break;
		case 'MIEMM': $curso_id='744';break;
		case 'MIEQ': $curso_id='745';break; 
		default : echo 'Error';exit();
	}
	switch($_POST['periodo'])
	{
		case '1': $periodo_id='2';break;
		case '2': $periodo_id='3';break;
		default : echo 'Error';exit();
	}
	
	
//Query FEUP
	
	
	
	
	//Iniciar Sessao dos posts
    $ch = curl_init(); 
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch,CURLOPT_COOKIEJAR ,null); 
	
	//POST para fazer login
	$url= 'https://sigarra.up.pt/feup/pt/vld_validacao.validacao';
	$fieldstr = 'p_user='.$username.'&p_pass='.$password.'&p_app=162&p_amo=55&p_address=web_page.Inicial'; //O App e o Amo pertencem ao form hidden de login, talvez seja preciso ir ao homepage buscalos primeiro
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_POST,5);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
    $loginresult = curl_exec($ch);
	
	
	
	
	
	//POST para vizualizar o curso e ir buscar o plano de curso
	$url= 'https://sigarra.up.pt/feup/pt/cur_geral.cur_view';
	$fieldstr = 'pv_curso_id='.$curso_id.'&pv_ano_lectivo='.$anoletivo;
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_POST,3);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
    $cursoresult = curl_exec($ch);
	
	
	//Parse para ir buscar o ID do plano
	$dom = new DOMDocument;
	@$dom->loadHTML($cursoresult);
	
	$xp = new DOMXpath($dom);
	$nodes = $xp->query('//div[@class="curso-informacoes"]/div[2]/ul/li/a');
	$str=$nodes->item(0)->attributes->getNamedItem("href")->nodeValue;
	$j=strpos($str,'=')+1; //ir buscar o primeiro argumento do link
	$plano_id=substr($str,$j,strpos($str,'&')-$j);
	//echo  $plano_id;
	
	
	//Post para vizualizar o plano 
	$url= 'https://sigarra.up.pt/feup/pt/cur_geral.cur_planos_estudos_view';
	$fieldstr = 'pv_plano_id='.$plano_id.'&pv_ano_lectivo='.$anoletivo;
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_POST,3);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
    $planoresult = curl_exec($ch);
	
	$cadeiras=array();
	
	
	//Parse para sacar as cadeiras (ocurrencia) 
	$dom = new DOMDocument;
	@$dom->loadHTML($planoresult);
	
	$xp = new DOMXpath($dom);
	//cadeiras normais, sacar sigla da cadeira, id da ocurrencia e semestre
	$nodes = $xp->query('//table/tr/td/table/tr/td[@width="50%"]/table');
	for ($i=0;$i<$nodes->length;$i++)
	{
		$nodetable=$nodes->item($i);
		$nodestr=$xp->query('./tr',$nodetable);
		for ($j=2;$j<$nodestr->length;$j++)//começar a 2, o 0 e 1 é o header da tabela
		{
			$nodestd=$xp->query('./td',$nodestr->item($j));
			$cadeira["sigla"]=$nodestd->item(1)->nodeValue;
			if ($cadeira["sigla"]!=""){ //fazer esta confirmação
				$str=$xp->query('./a',$nodestd->item(2))->item(0)->attributes->getNamedItem("href")->nodeValue;
				$k=strpos($str,'=')+1;
				$cadeira["ocurrencia"]=substr($str,$k);
				$cadeira["semestre"]=$i%2 +1;
				
				//procurar repetidos (não é preciso no caso do MIEIC)
				$fl=true;
				foreach ($cadeiras as $cad)
				{
					if($cad["ocurrencia"]==$cadeira["ocurrencia"])
					{
						$fl=false;
						break;
					}
				}
				if ($fl) {
					//Post para vizualizar o plano 
					$url= 'https://sigarra.up.pt/feup/pt/it_listagem.lista_turma_disciplina';
					$fieldstr = 'pv_curso_id='.$curso_id.'&pv_ocorrencia_id='.$cadeira["ocurrencia"].'&pv_ano_lectivo='.$anoletivo.'&pv_periodo_id='.$cadeira["semestre"];
					curl_setopt($ch,CURLOPT_URL,$url);
					curl_setopt($ch,CURLOPT_POST,3);
					curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
					$alunosresult = curl_exec($ch);
					$cadeira["alunos"]=array();


					//Parse para sacar os alunos
					$dom2 = new DOMDocument;
					@$dom2->loadHTML($alunosresult);

					$xp2 = new DOMXpath($dom2);
					//ir buscar todas as turmas
					$nodes2 = $xp2->query('//div[@id="conteudo"]/table');
					for ($x=1;$x<$nodes2->length;$x++) //começar na tabela 1, o 0 é a lista das turmas
					{
						$nodetable2=$nodes2->item($x);
						$nodestd=$xp2->query('./tr/td[2]',$nodetable2); //ir diretamente buscar o segundo TD de cada row
						for ($y=0;$y<$nodestd->length;$y++) //começar a 0, a 1a row é não tem TDs logo não obdece à expressão
						{
							$aluno=$nodestd->item($y)->nodeValue;
							array_push($cadeira["alunos"], $aluno);
							
						}
					}
					array_push($cadeiras,$cadeira);
				}
			}
		}
	}
	//cadeiras optativas, sacar sigla da cadeira, id da ocurrencia e semestre
	$nodes = $xp->query('//div[@id]/table/tr/td/table');
	for ($i=0;$i<$nodes->length;$i++)
	{
		$nodetable=$nodes->item($i);
		$nodestr=$xp->query('./tr',$nodetable);
		for ($j=1;$j<$nodestr->length;$j++)//começar a 1, o 0 é o header da tabela
		{
			$nodestd=$xp->query('./td',$nodestr->item($j));
			$cadeira["sigla"]=$nodestd->item(1)->nodeValue;
			if ($cadeira["sigla"]!=""){ //fazer esta confirmação
				$str=$xp->query('./a',$nodestd->item(2))->item(0)->attributes->getNamedItem("href")->nodeValue;
				$k=strpos($str,'=')+1;
				$cadeira["ocurrencia"]=substr($str,$k);
				if ($nodestd->item(5)->nodeValue=="1S")$cadeira["semestre"]=1;
				else $cadeira["semestre"]=2;
				//procurar repetidos (não é preciso no caso do MIEIC)
				$fl=true;
				foreach ($cadeiras as $cad)
				{
					if($cad["ocurrencia"]==$cadeira["ocurrencia"])
					{
						$fl=false;
						break;
					}
				}
				if ($fl) {
					//Post para vizualizar o plano 
					$url= 'https://sigarra.up.pt/feup/pt/it_listagem.lista_turma_disciplina';
					$fieldstr = 'pv_curso_id='.$curso_id.'&pv_ocorrencia_id='.$cadeira["ocurrencia"].'&pv_ano_lectivo='.$anoletivo.'&pv_periodo_id='.$cadeira["semestre"];
					curl_setopt($ch,CURLOPT_URL,$url);
					curl_setopt($ch,CURLOPT_POST,3);
					curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
					$alunosresult = curl_exec($ch);
					$cadeira["alunos"]=array();


					//Parse para sacar os alunos
					$dom2 = new DOMDocument;
					@$dom2->loadHTML($alunosresult);

					$xp2 = new DOMXpath($dom2);
					//ir buscar todas as turmas
					$nodes2 = $xp2->query('//div[@id="conteudo"]/table');
					for ($x=1;$x<$nodes2->length;$x++) //começar na tabela 1, o 0 é a lista das turmas
					{
						$nodetable2=$nodes2->item($x);
						$nodestd=$xp2->query('./tr/td[2]',$nodetable2); //ir diretamente buscar o segundo TD de cada row
						for ($y=0;$y<$nodestd->length;$y++) //começar a 0, a 1a row é não tem TDs logo não obdece à expressão
						{
							$aluno=$nodestd->item($y)->nodeValue;
							array_push($cadeira["alunos"], $aluno);
							
						}
					}
					
					array_push($cadeiras,$cadeira);
				}
			}
		}
	}
	
	
	//percorrer as cadeiras obtidas e sacar lista de alunos
	foreach ($cadeiras as $cad)
	{
		//Post para vizualizar o plano 
		$url= 'https://sigarra.up.pt/feup/pt/it_listagem.lista_turma_disciplina';
		$fieldstr = 'pv_curso_id='.$curso_id.'&pv_ocorrencia_id='.$cad["ocurrencia"].'&pv_ano_lectivo='.$anoletivo.'&pv_periodo_id='.$cad["semestre"];
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,3);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
		$alunosresult = curl_exec($ch);
		$cad["alunos"]=array();


		//Parse para sacar os alunos
		$dom = new DOMDocument;
		@$dom->loadHTML($alunosresult);

		$xp = new DOMXpath($dom);
		//ir buscar todas as turmas
		$nodes = $xp->query('//div[@id="conteudo"]/table');
		for ($i=1;$i<$nodes->length;$i++) //começar na tabela 1, o 0 é a lista das turmas
		{
			$nodetable=$nodes->item($i);
			$nodestd=$xp->query('./tr/td[2]',$nodetable); //ir diretamente buscar o segundo TD de cada row
			for ($j=0;$j<$nodestd->length;$j++) //começar a 0, a 1a row é não tem TDs logo não obdece à expressão
			{
				$aluno=$nodestd->item($j)->nodeValue;
				$cad["alunos"][] = $aluno;
				
			}
		}
	}
	
	
	
	
	
		/*código de sacar horários, aqui para referencia/ajuda
		//POST para sacar o horario
		$url= 'https://sigarra.up.pt/feup/pt/hor_geral.turmas_view';
		$fieldstr = 'pv_turma_id='.$turma_id.'&pv_periodos='.$periodo_id.'&pv_ano_lectivo='.$anoletivo; 
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,3);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldstr);
		$horarioresult=curl_exec($ch);
		
		//SCRAP
		$dom2 = new DOMDocument;
		@$dom2->loadHTML($horarioresult);
		//Scrap todas as rows
		$xp2 = new DOMXpath($dom2);
		$nodesrow = $xp2->query('//table[@class="tabela"]/tr');
		
		$rowspan=array(0,0,0,0,0,0,0,0); //rowspan para as colunas, 0->horas 1-6-> segunda a sabado, 7-> força a saida do while pk e sempre 0
		//Comecar as 8 da manha
		$hora=8.0;
		//Comecar na row 2, a 1 tem os dias (o xpath comeca a 1 e nao a 0, por isso aumentar o ciclo para <= tb)
		for($row=2; $row<=$nodesrow->length;$row++)
		{	
			$nodescol=$xp2->query('//div/table[@class="tabela"]/tr['.$row.']/td'); //Nao usar child, por causa dos whitespaces nodes.
			$dia=1;
			for ($col=1;$col<$nodescol->length;$col++)
			{
				while ($rowspan[$dia]>0)
				{ //compensar os dias que sao comidos pelo rowspan
					$rowspan[$dia]--;
					$dia++;
				}
				
				//scrap do td
				$nodetd=$nodescol->item($col);
				$tipo=$nodetd->attributes->getNamedItem('class')->nodeValue;
				if ($tipo=='TP'||$tipo=='T'||$tipo=='P'||$tipo=='L')
				{	//se for uma aula
					//contar o rowspan/duracao da aula
					$aduracao=$nodetd->attributes->getNamedItem('rowspan')->nodeValue;
					$rowspan[$dia]=$aduracao-1;
					//nome da aula
					$anome=$xp2->query('./b/acronym/@title',$nodetd)->item(0)->nodeValue;
					$asigla=$xp2->query('./b/acronym/a',$nodetd)->item(0)->nodeValue;	
					//sala -> usar // em vez de / nestes querys porque os br's fo**m tudo (literalmente)
					$asala=$xp2->query('.//table/tr/td/a',$nodetd)->item(0)->nodeValue;
					//professor 
					$aprofsig=$xp2->query('.//table/tr/td[3]//a',$nodetd)->item(0)->nodeValue;
					$aprofnome=$xp2->query('.//table/tr/td[3]/acronym/@title',$nodetd)->item(0)->nodeValue;
					//turma da cadeira
					$turma_cadeira=$xp2->query('.//span/a',$nodetd)->item(0)->nodeValue;
					//passar tudo para o array
					if (!is_array($horarios[$asigla][$tipo])) $horarios[$asigla][$tipo]=array();
					array_push($horarios[$asigla][$tipo], array('dia'=>$dia,'hora'=>$hora,'nome'=>$anome,'sigla'=>$asigla,'tipo'=>$tipo,'turma'=>$turma_nome,'turmac'=>$turma_cadeira,'duracao'=>$aduracao,'sala'=>$asala,'profsig'=>$aprofsig,'prof'=>$aprofnome));
					//gravar o nome da cadeira dentro do objecto da cadeira para facilitar extraçao no js
					$horarios[$asigla]['nome']=$anome;
					
					//echo "<p>".$dia." ".$hora." ".$anome." ".$asigla." ".$tipo." ".$turma_nome." ".$turma_cadeira." ".$aduracao." ".$asala." ".$aprofsig." ".$aprofnome."</p>";
				}
				
				$dia++;
			}
			while ($rowspan[$dia]>0)
			{//executar isto no final mais uma vez, podem haver colunas no final com rowspan
				$rowspan[$dia]--;
				$dia++;
			}
			$hora=$hora+0.5;
		}
		*/

	
	
	//fechar a sessao
    curl_close($ch);
	$json[cadeiras]=$cadeiras;
	//$filename=''.$_POST['curso'].$_POST['anolectivo'].$_POST['periodo'].'.json';
	//file_put_contents($filename,json_encode($horarios));
	//chmod($filename,0664);
	echo json_encode($json);
	
?>