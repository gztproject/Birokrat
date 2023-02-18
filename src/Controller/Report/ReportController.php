<?php
namespace App\Controller\Report;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Organization\Organization;
use App\Entity\Transaction\Transaction;
use App\Entity\Report\DDDDDD;
use App\Entity\Report\Bilanca;
use App\Repository\Transaction\TransactionRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use App\Entity\Report\Turnout;
use App\Entity\Konto\Enumerators\KontoTypes;

class ReportController extends AbstractController
{
    /**
     *
     * @Route("/dashboard/report", methods={"GET"}, name="report_index")
     */
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $dateFrom = $request->query->get('dateFrom', null);
        $dateTo = $request->query->get('dateTo', null);
        $orgId = $request->query->get('organization', null);
        $orgId = $orgId === "" ? null : $orgId;

        return $this->render('dashboard/report/index.html.twig', [            
            'overviewReport' => $this->getOverviewReport($orgId, $dateFrom, $dateTo, $em),
            'kontoReport' => $this->getKontoReport($orgId, $dateFrom, $dateTo, $em),
            'clientReport' => $this->getClientReport($orgId, $dateFrom, $dateTo, $em),
            'partnerReport' => $this->getPartnerReport($orgId, $dateFrom, $dateTo, $em)
        ]);
    }

    /**
     *
     * @Route("/dashboard/report/dddddd", methods={"GET"}, name="report_dddddd")
     */
    public function ddddddReport(Request $request, EntityManagerInterface $em): Response
    {
        $dateFrom = $request->query->get('dateFrom', null);
        $dateTo = $request->query->get('dateTo', null);
        $orgId = $request->query->get('organization', null);
        $orgId = $orgId === "" ? null : $orgId;

        return $this->render('dashboard/report/dddddd.html.twig', [
            'dddReport' => $this->getDDDReport($orgId, $dateFrom, $dateTo, $em)
        ]);
    }

    /**
     *
     * @Route("/dashboard/report/blianca", methods={"GET"}, name="report_bilanca")
     */
    public function bilancaReport(Request $request, EntityManagerInterface $em): Response
    {
        $dateTo = $request->query->get('dateTo', null);
        $dateToLY = strtotime(date("c", $dateTo) . "-1 year");
        $orgId = $request->query->get('organization', null);
        $orgId = $orgId === "" ? null : $orgId;

        return $this->render('dashboard/report/bilanca.html.twig', [
            'bilancaReportThisYear' => $this->getBilancaReport($orgId, $dateTo, $em),
            'bilancaReportLastYear' => $this->getBilancaReport($orgId, $dateToLY, $em)
        ]);
    }

    /**
     *
     * @Route("/dashboard/report/turnout", methods={"GET"}, name="report_turnout")
     */
    public function izkazIzidaReport(Request $request, EntityManagerInterface $em): Response
    {
        $dateFrom = $request->query->get('dateFrom', null);
        $dateFromLY = strtotime(date("c", $dateFrom) . "-1 year");
        $dateTo = $request->query->get('dateTo', null);
        $dateToLY = strtotime(date("c", $dateTo) . "-1 year");
        $orgId = $request->query->get('organization', null);
        $orgId = $orgId === "" ? null : $orgId;

        return $this->render('dashboard/report/turnout.html.twig', [
            'turnoutReportThisYear' => $this->getTurnoutReport($orgId, $dateFrom, $dateTo, $em),
            'turnoutReportLastYear' => $this->getTurnoutReport($orgId, $dateFromLY, $dateToLY, $em)
        ]);
    }

    private function getOverviewReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): array
    {
        $invoices = 0;
        $invoicesKontos = [];
        $taxableSubventions = 0;
        $taxableSubventionsKontos = [];
        $otherSubventions = 0;
        $otherSubventionsKontos = [];
        $otherIncomes = 0;
        $otherIncomesKontos = [];

        $incomingInvoices = 0;
        $incomingInvoicesKontos = [];
        $dailyExpenses = 0;
        $dailyExpensesKontos = [];
        $socialSecurity = 0;
        $socialSecurityKontos = [];
        $incomeTax = 0;
        $incomeTaxKontos = [];
        $otherExpenses = 0;
        $otherExpensesKontos = [];

        $bank = 0;
        $debts = 0;
        $cash = 0;

        $qb = $em->createQueryBuilder();
        $qb->select([
            'k.id',
            'kc.number AS categoryNumber',
            'k.name AS kontoName',
            'k.number AS kontoNumber',
            'k.type AS type',
            'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
            'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'
        ])
            ->from('App\Entity\Konto\Konto', 'k', 'k.id')
            ->leftJoin('App\Entity\Konto\KontoCategory', 'kc', 'WITH', 'k.category = kc.id')
            ->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', '(t.debitKonto = k.id OR t.creditKonto = k.id)')
            ->where('t.hidden = 0');
        if ($organizationId !== null)
            $qb->andWhere('t.organization = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('t.date >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('t.date <= :dateTo');
        $qb->groupBy('k.id');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));
        $query = $qb->getQuery();
        $result = $query->getArrayResult();

        foreach ($result as $res) {
            switch ($res['categoryNumber']) {

                // Income
                case 76:
                case 77:
                case 78:
                case 79:
                    if ($res['kontoNumber'] == 760) {
                        $invoices += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $invoicesKontos[$res['kontoNumber']] = $res['kontoName'];
                    } else if ($res['kontoNumber'] == 768) {
                        $taxableSubventions += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $taxableSubventionsKontos[$res['kontoNumber']] = $res['kontoName'];
                    } else if ($res['kontoNumber'] == 785) {
                        $otherSubventions += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $otherSubventionsKontos[$res['kontoNumber']] = $res['kontoName'];
                    } else {
                        $otherIncomes += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $otherIncomesKontos[$res['kontoNumber']] = $res['kontoName'];
                    }
                    break;

                // Expenses
                case 40:
                case 41:
                    $incomingInvoices += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $incomingInvoicesKontos[$res['kontoNumber']] = $res['kontoName'];
                    break;

                case 43:
                case 44:
                case 45:
                case 47:
                case 48:
                case 49:
                case 70:
                case 71:
                case 72:
                case 74:
                case 75:
                    if ($res['kontoNumber'] == 486) {
                        $dailyExpenses += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $dailyExpensesKontos[$res['kontoNumber']] = $res['kontoName'];
                    } else if ($res['kontoNumber'] == 484) {
                        $socialSecurity += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $socialSecurityKontos[$res['kontoNumber']] = $res['kontoName'];
                    } else {
                        $otherExpenses += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $otherExpensesKontos[$res['kontoNumber']] = $res['kontoName'];
                    }
                    break;

                case 81:
                    if ($res['kontoNumber'] == 810) {
                        $incomeTax += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                        $incomeTaxKontos[$res['kontoNumber']] = $res['kontoName'];
                    }
                    break;

                case 11:
                    $bank += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 12:
                    $debts += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 28:
                    $debts += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 91:
                    $cash += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                default:
                    break;
            }
        }
        return [
            'invoices' => $invoices,
            'invoicesKontos' => $invoicesKontos,
            'taxableSubventions' => $taxableSubventions,
            'taxableSubventionsKontos' => $taxableSubventionsKontos,
            'otherSubventions' => $otherSubventions,
            'otherSubventionsKontos' => $otherSubventionsKontos,
            'otherIncomes' => $otherIncomes,
            'otherIncomesKontos' => $otherIncomesKontos,
            'income' => $invoices + $taxableSubventions + $otherIncomes + $otherSubventions,

            'incomingInvoices' => $incomingInvoices,
            'incomingInvoicesKontos' => $incomingInvoicesKontos,
            'socialSecurity' => $socialSecurity,
            'socialSecurityKontos' => $socialSecurityKontos,
            'dailyExpenses' => $dailyExpenses,
            'dailyExpensesKontos' => $dailyExpensesKontos,
            'incomeTax' => $incomeTax,
            'incomeTaxKontos' => $incomeTaxKontos,
            'other' => $otherExpenses,
            'otherKontos' => $otherExpensesKontos,
            'expenses' => $incomingInvoices + $socialSecurity + $dailyExpenses + $otherExpenses,

            'bank' => $bank,
            'debts' => $debts,
            'cash' => $cash,
            'outcome' => ($invoices + $taxableSubventions + $otherIncomes + $otherSubventions) - ($incomingInvoices + $socialSecurity + $dailyExpenses + $otherExpenses)
        ];
    }

    private function getKontoReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): array
    {
        $qb = $em->createQueryBuilder();
        $qb->select([
            'k.id',
            'k.number',
            'k.name',
            'k.type AS type',
            'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
            'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'
        ])
            ->from('App\Entity\Konto\Konto', 'k', 'k.id')
            ->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', '(t.debitKonto = k.id OR t.creditKonto = k.id)')
            ->where('t.id IS NOT NULL'); // ->andWhere ( 't.hidden = 0' );
        if ($organizationId !== null)
            $qb->andWhere('t.organization = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('t.date >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('t.date <= :dateTo');
        $qb->addGroupBy('k.id');
        ;
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));
        $query = $qb->getQuery();
        $result = $query->getArrayResult();
        $report = [
            'kontos' => []
        ];
        foreach ($result as $konto) {
            $konto['sum'] = $konto['type'] == KontoTypes::active ? $konto['debit'] - $konto['credit'] : $konto['credit'] - $konto['debit'];
            array_push($report['kontos'], $konto);
        }
        return $report;
    }

    private function getClientReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): array
    {
        // $sql = 'SELECT c.id, c.name, COUNT(i.id), SUM(i.total_price) AS total, SUM(CASE WHEN i.state=30 THEN i.total_price ELSE 0 END) AS paid,
        // SUM(CASE WHEN i.state=20 THEN i.total_price ELSE 0 END) AS open
        // FROM partner AS c
        // LEFT OUTER JOIN invoice AS i ON i.recepient_id = c.id AND i.state IN (20, 30)
        // WHERE c.is_client = 1
        // AND i.issuer_id IN ("2acaf66b-4103-11e9-ad9e-68f7280713e5", "2accfd44-4103-11e9-ad9e-68f7280713e5")
        // GROUP BY c.id
        // ORDER BY total DESC';
        $qb = $em->createQueryBuilder();
        $qb->select([
            'c.id',
            'c.name',
            'COUNT(i.id) AS number',
            'SUM(i.totalPrice) AS total',
            'SUM(CASE WHEN i.state = 30 THEN i.totalPrice ELSE 0 END) AS paid',
            'SUM(CASE WHEN i.state = 20 THEN i.totalPrice ELSE 0 END) AS open'
        ])
            ->from('App\Entity\Organization\Partner', 'c', 'c.id')
            ->leftJoin('App\Entity\Invoice\Invoice', 'i', 'WITH', 'i.recepient = c.id AND i.state IN (20, 30)')
            ->where('c.isClient = 1');
        if ($organizationId !== null)
            $qb->andWhere('i.issuer = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('i.dateOfIssue >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('i.dateOfIssue <= :dateTo');
        $qb->addGroupBy('c.id')->addOrderBy('total', 'DESC');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));

        $query = $qb->getQuery();
        $result = $query->getArrayResult();
        $totalNumber = 0;
        $totalTotal = 0;
        $totalPaid = 0;
        $totalOpen = 0;
        $totalPercent = 0;
        $clients = [];
        foreach ($result as $c) {
            $totalNumber += $c['number'];
            $totalTotal += $c['total'];
            $totalPaid += $c['paid'];
            $totalOpen += $c['open'];
        }
        foreach ($result as $c) {
            $c['percent'] = $c['total'] / $totalTotal;
            $totalPercent += $c['percent'];
            array_push($clients, $c);
        }

        $sum = [
            'number' => $totalNumber,
            'total' => $totalTotal,
            'paid' => $totalPaid,
            'open' => $totalOpen,
            'percent' => $totalPercent
        ];

        $report = [
            'clients' => $clients,
            'sum' => $sum
        ];

        return $report;
    }

    private function getPartnerReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): array
    {
        // $sql = 'SELECT c.id, c.name, COUNT(i.id), SUM(i.total_price) AS total, SUM(CASE WHEN i.state=30 THEN i.total_price ELSE 0 END) AS paid,
        // SUM(CASE WHEN i.state=20 THEN i.total_price ELSE 0 END) AS open
        // FROM partner AS c
        // LEFT OUTER JOIN invoice AS i ON i.recepient_id = c.id AND i.state IN (20, 30)
        // WHERE c.is_client = 1
        // AND i.issuer_id IN ("2acaf66b-4103-11e9-ad9e-68f7280713e5", "2accfd44-4103-11e9-ad9e-68f7280713e5")
        // GROUP BY c.id
        // ORDER BY total DESC';
        $qb = $em->createQueryBuilder();
        $qb->select([
            'c.id',
            'c.name',
            'COUNT(i.id) AS number',
            'SUM(i.price) AS total',
            'SUM(CASE WHEN i.state = 30 THEN i.price ELSE 0 END) AS paid',
            'SUM(CASE WHEN i.state = 20 THEN i.price ELSE 0 END) AS open'
        ])
            ->from('App\Entity\Organization\Partner', 'c', 'c.id')
            ->leftJoin('App\Entity\IncomingInvoice\IncomingInvoice', 'i', 'WITH', 'i.issuer = c.id AND i.state IN (20, 30)')
            ->where('c.isSupplier = 1');
        if ($organizationId !== null)
            $qb->andWhere('i.recepient = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('i.dateOfIssue >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('i.dateOfIssue <= :dateTo');
        $qb->addGroupBy('c.id')->addOrderBy('total', 'DESC');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));

        $query = $qb->getQuery();
        $result = $query->getArrayResult();
        $totalNumber = 0;
        $totalTotal = 0;
        $totalPaid = 0;
        $totalOpen = 0;
        $totalPercent = 0;
        $clients = [];
        foreach ($result as $c) {
            $totalNumber += $c['number'];
            $totalTotal += $c['total'];
            $totalPaid += $c['paid'];
            $totalOpen += $c['open'];
        }
        foreach ($result as $c) {
            $c['percent'] = $c['total'] / $totalTotal;
            $totalPercent += $c['percent'];
            array_push($clients, $c);
        }

        $sum = [
            'number' => $totalNumber,
            'total' => $totalTotal,
            'paid' => $totalPaid,
            'open' => $totalOpen,
            'percent' => $totalPercent
        ];

        $report = [
            'clients' => $clients,
            'sum' => $sum
        ];

        return $report;
    }

    private function getDDDReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): DDDDDD
    {
        $report = new DDDDDD();
        $qb = $em->createQueryBuilder();
        $qb->select([
            'k.id',
            'kc.number AS categoryNumber',
            'k.name',
            'k.number AS kontoNumber',
            'k.type AS type',
            'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
            'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'
        ])
            ->from('App\Entity\Konto\Konto', 'k', 'k.id')
            ->leftJoin('App\Entity\Konto\KontoCategory', 'kc', 'WITH', 'k.category = kc.id')
            ->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', '(t.debitKonto = k.id OR t.creditKonto = k.id)')
            ->Where('t.hidden = 0');
        if ($organizationId !== null)
            $qb->andWhere('t.organization = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('t.date >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('t.date <= :dateTo');
        $qb->groupBy('k.id');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));
        $query = $qb->getQuery();
        $result = $query->getArrayResult();

        foreach ($result as $res) {
            switch ($res['categoryNumber']) {
                // Income
                case 76:
                case 77:
                    $report->a += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;

                case 78:
                    $report->a += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->b8 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;

                // Expenses
                case 40:
                case 41:
                case 43:
                case 44:
                case 47:
                case 48:
                case 49:
                    $report->e += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;

                // Interests
                case 45:
                    $report->e += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->f13 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }

            switch ($res['kontoNumber']) {
                case 810:
                    $report->y += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }
        }

        // The constants should be updated yearly...
        $exemption = 0;
        // 2019
        if (explode("-", date('Y-m-d G:i:s', $dateFrom))[0] == 2019 && explode("-", date('Y-m-d G:i:s', $dateTo))[0] == 2019)
            $exemption = ($report->a <= 11166.37) ? 6519.82 : (($report->a <= 13316.83) ? (3302.7 + (19922.15 - (1.49601 * $report->a))) : 3302.7);
        // 2020
        if (explode("-", date('Y-m-d G:i:s', $dateFrom))[0] == 2020 && explode("-", date('Y-m-d G:i:s', $dateTo))[0] == 2020)
            $exemption = ($report->a <= 13316.83) ? (3500.0 + (18700.38 - (1.40427 * $report->a))) : 3500.0;

        $report->q1 = $exemption;
        $report->u = 0;
        $report->v = 0;

        $report->recalculate();
        return $report;
    }

    private function getBilancaReport(?String $organizationId, ?String $date, EntityManagerInterface $em): Bilanca
    {
        $report = new Bilanca();
        $qb = $em->createQueryBuilder();
        $qb->select([
            'k.id',
            'kc.number AS categoryNumber',
            'k.name',
            'k.number AS kontoNumber',
            'k.type AS type',
            'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
            'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'
        ])
            ->from('App\Entity\Konto\Konto', 'k', 'k.id')
            ->leftJoin('App\Entity\Konto\KontoCategory', 'kc', 'WITH', 'k.category = kc.id')
            ->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', 't.debitKonto = k.id OR t.creditKonto = k.id')
            ->Where('t.hidden = 0');
        if ($organizationId !== null)
            $qb->andWhere('t.organization = :orgId');
        if ($date !== null)
            $qb->andWhere('t.date <= :date');
        $qb->groupBy('k.id');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        // if ($dateFrom !== null)
        // $qb->setParameter ( 'dateFrom', date ( 'Y-m-d G:i:s', $dateFrom ) );
        if ($date !== null)
            $qb->setParameter('date', date('Y-m-d G:i:s', $date));
        $query = $qb->getQuery();
        $result = $query->getArrayResult();

        foreach ($result as $res) {
            switch ($res['categoryNumber']) {
                case 0:
                case 8:
                    $report->p004 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 2:
                case 3:
                case 4:
                case 5:
                    $report->p010 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 1:
                    $report->p018 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 6:
                    $report->p020 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 7:
                    $report->p024 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 8:
                    $report->p027 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 67:
                    $report->p033 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 30:
                case 31:
                case 32:
                    $report->p035 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 60:
                    $report->p036 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 61:
                case 63:
                    $report->p037 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 65:
                case 66:
                    $report->p038 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 17:
                    $report->p041 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 18:
                    $report->p045 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 12:
                case 14:
                case 15:
                case 16:
                    $report->p048 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 10:
                case 11:
                    $report->p052 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 19:
                    $report->p053 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 90:
                    $report->p058 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 98:
                    $report->p080 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 21:
                    $report->p086 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 27:
                    $report->p087 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 22:
                case 23:
                case 24:
                case 25:
                case 26:
                case 28:
                    $report->p091 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 29:
                    $report->p095 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 94:
                    $report->p067 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 95:
                    $report->p301 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }

            switch ($res['kontoNumber']) {
                case 1:
                    $report->p010 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p004 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 7:
                    $report->p009 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p004 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 80:
                case 81:
                    $report->p010 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p004 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p027 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 76:
                case 77:
                case 78:
                    $report->p024 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p045 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit']; // Not really sure but we don't really use this kontos anyway.
                    break;
                case 131:
                    $report->p004 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 132:
                    $report->p039 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 86:
                    $report->p048 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p027 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 990:
                case 991:
                case 992:
                case 993:
                case 994:
                    $report->p054 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 918:
                    $report->p060a += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 919:
                    $report->p060b += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 935:
                    $report->p070 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 937:
                    $report->p071 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 960:
                case 961:
                case 962:
                case 963:
                case 964:
                case 965:
                case 969:
                    $report->p073 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 966:
                case 967:
                case 968:
                    $report->p074 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 974:
                case 975:
                case 976:
                case 979:
                    $report->p076 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 970:
                case 971:
                case 972:
                case 973:
                    $report->p087 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 995:
                case 996:
                case 997:
                case 998:
                case 999:
                    $report->p096 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }
        }

        $report->calculate();
        return $report;
    }

    private function getTurnoutReport(?String $organizationId, ?String $dateFrom, ?String $dateTo, EntityManagerInterface $em): Turnout
    {
        $report = new Turnout();
        $qb = $em->createQueryBuilder();
        $qb->select([
            'k.id',
            'kc.number AS categoryNumber',
            'k.name',
            'k.number AS kontoNumber',
            'k.type AS type',
            'SUM(CASE WHEN t.debitKonto = k.id THEN t.sum ELSE 0 END) AS debit',
            'SUM(CASE WHEN t.creditKonto = k.id THEN t.sum ELSE 0 END) AS credit'
        ])
            ->from('App\Entity\Konto\Konto', 'k', 'k.id')
            ->leftJoin('App\Entity\Konto\KontoCategory', 'kc', 'WITH', 'k.category = kc.id')
            ->leftJoin('App\Entity\Transaction\Transaction', 't', 'WITH', 't.sum >= 0 AND (t.debitKonto = k.id OR t.creditKonto = k.id)')
            ->Where('t.hidden = 0');
        if ($organizationId !== null)
            $qb->andWhere('t.organization = :orgId');
        if ($dateFrom !== null)
            $qb->andWhere('t.date >= :dateFrom');
        if ($dateTo !== null)
            $qb->andWhere('t.date <= :dateTo');
        $qb->groupBy('k.id');
        if ($organizationId !== null)
            $qb->setParameter('orgId', $organizationId);
        if ($dateFrom !== null)
            $qb->setParameter('dateFrom', date('Y-m-d G:i:s', $dateFrom));
        if ($dateTo !== null)
            $qb->setParameter('dateTo', date('Y-m-d G:i:s', $dateTo));
        $query = $qb->getQuery();
        $result = $query->getArrayResult();

        foreach ($result as $res) {
            switch ($res['categoryNumber']) {
                case 60:
                case 63:
                    $sum = $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p121 += $sum > 0 ? $sum : 0;
                    $report->p122 += $sum < 0 ? $sum : 0;
                    break;
                case 79:
                    $report->p123 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 40:
                    $report->p130 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 41:
                    $report->p134 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 43:
                    $report->p145 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 44:
                    $report->p148b += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }

            switch ($res['kontoNumber']) {
                case 760:
                case 762:
                    $report->p111 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 761:
                case 763:
                    $report->p115 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 619:
                    $sum = $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    $report->p121 += $sum > 0 ? $sum : 0;
                    $report->p122 += $sum < 0 ? $sum : 0;
                    break;
                case 768:
                    $report->p124 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 764:
                case 765:
                case 766:
                case 767:
                case 769:
                    $report->p125 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 702:
                    $report->p129 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 470:
                case 471:
                case 473:
                case 475:
                case 476:
                case 477:
                    $report->p140 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 472:
                    $report->p141 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 474:
                    $report->p142 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 478:
                    $report->p143 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 720:
                    $report->p146 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 721:
                    $report->p147 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 484:
                    $report->p148a += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 785:
                    $report->p179 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 489:
                case 486:
                    $report->p148b += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 770:
                case 771:
                case 772:
                case 773:
                    $report->p155 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 774:
                case 775:
                    $report->p160 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 776:
                case 777:
                    $report->p163 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 748:
                    $report->p168 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 450:
                    $report->p169 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit']; // Not sure if this should be here but we need to account for it somewhere...
                    break;
                case 743:
                    $report->p169 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 746:
                    $report->p174 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
                case 789:
                    $report->p180 += $res['type'] == KontoTypes::active ? $res['debit'] - $res['credit'] : $res['credit'] - $res['debit'];
                    break;
            }
        }

        $report->calculate();
        return $report;
    }

}
