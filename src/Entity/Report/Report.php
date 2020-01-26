<?php

namespace App\Entity\Report;


class Report
{
	/**
	 * 1
	 */
	public $a;
    public $a1 = 0;
    
    /**
     * 2
     */
    public $b;
    public $b1 = 0;
    public $b2 = 0;
    public $b3 = 0;
    public $b4 = 0;
    public $b5 = 0;
    public $b6 = 0;
    public $b7 = 0;
    
    /**
     * 3
     */
    public $c;
    public $c1 = 0;
    public $c2 = 0;
    public $c3 = 0;
    public $c4 = 0;
    public $c5 = 0;
    public $c6 = 0;
    
    /**
     * 4
     */
    public $d;
    
    /**
     * 5
     */
    public $e;
    
    /**
     * 6
     */
    public $f;
    public $f1 = 0;
    public $f2 = 0;
    public $f3 = 0;
    public $f4 = 0;
    public $f5 = 0;
    public $f6 = 0;
    public $f7 = 0;
    public $f8 = 0;
    public $f9 = 0;
    public $f10 = 0;
    public $f11 = 0;
    public $f12 = 0;
    public $f13 = 0;
    public $f14 = 0;
    public $f15 = 0;
    public $f16 = 0;
    public $f17 = 0;
    public $f18 = 0;
    public $f19 = 0;
    public $f20 = 0;
    public $f21 = 0;
    public $f22 = 0;
    public $f23 = 0;
    public $f24 = 0;
    public $f25 = 0;
    public $f26 = 0;
    public $f27 = 0;
    public $f28 = 0;
    public $f29 = 0;

    /**
     * 7
     */
    public $g;
    public $g1 = 0;
    public $g2 = 0;
    public $g3 = 0;
    public $g4 = 0;
    public $g5 = 0;
    public $g6 = 0;
    public $g7 = 0;
    public $g8 = 0;
    
    /**
     * 8
     */
    public $h;
    
    /**
     * 9
     */
    public $i;
    
    /**
     * 10
     */
    public $j;
    
    /**
     * 11
     */
    public $k;
    public $k1 = 0;
    public $k2 = 0;
    public $k3 = 0;
    public $k4 = 0;
    
    /**
     * 12
     */
    public $l;
    public $l1 = 0;
    public $l2 = 0;
    public $l3 = 0;
    
    /**
     * 13
     */
    public $m;
    
    /**
     * 14
     */
    public $n;
    
    /**
     * 15
     */
    public $o;
    public $o1 = 0;
    public $o2 = 0;
    public $o3;
    public $o4 = 0;
    public $o5;
    public $o6 = 0;
    public $o7 = 0;
    public $o8 = 0;
    public $o9 = 0;
    public $o10 = 0;
    public $o11 = 0;
    public $o12;
    public $o13 = 0;
    public $o14 = 0;
    public $o15 = 0;
    public $o16 = 0;
    
    /**
     * 16
     */
    public $p;
    
    /**
     * 17
     */
    public $q;
    /**
     * Splošna davčna olajšava
     */
    public $q1 = 0;
    public $q2 = 0;
    public $q3 = 0;       
    
    /**
     * 18
     */
    public $r;
    
    /**
     * 19
     */
    public $s;
    
    /**
     * 20 AKONTACIJA DOHODNINE
     */ 
    public $t;
    public $t1;
    public $t1low;
    public $t1high;
    public $t1percent;
    public $t2;
    public $t2low;
    public $t2high;
    public $t2percent;
    
    /**
     * 21 Odbitek tujega davka
     */
    public $u;
    
    /**
     * 22 Povečanje davka zaradi spremembe odbitka tujega davka
     */
    public $v;
    
    /**
     * 23
     */
    public $w;
    
    /**
     * 24
     */
    public $x;
    
    /**
     * 25. Obračunana predhodna akontacija
     */
    public $y;
    
    /**
     * 26
     */
    public $z;
    
    /**
     * 27
     */
    public $aa;
    
    /**
     * 28
     */
    public $ab;
    
    /**
     * 29
     */
    public $ac;
    
    /**
     * 30
     */
    public $ad;
    
    /**
     * 31
     */
    public $ae;
    
    public function recalculate()
    {    	
    	$this->b = $this->b1 + $this->b2 + $this->b3 + $this->b4 + $this->b5 + $this->b6 + $this->b7;
    	
    	$this->c = $this->c1 + $this->c2 + $this->c3 + $this->c4 + $this->c5 + $this->c6;
    	
    	//DAVČNO PRIZNANI PRIHODKI (1 – 2 + 3) 
    	$this->d = $this->a - $this->b + $this->c;
    	
    	$this->f = $this->f1 + $this->f2 + $this->f3 + $this->f4 + $this->f5 + $this->f6 + $this->f7 + $this->f8 + $this->f9 + $this->f10 + 
    		$this->f11 + $this->f12 + $this->f13 + $this->f14 + $this->f15 + $this->f16 + $this->f17 + $this->f18 + $this->f19 + $this->f20 + 
    		$this->f21 + $this->f22 + $this->f23 + $this->f24 + $this->f25 + $this->f26 + $this->f27 + $this->f28 + $this->f29;
    	
    	$this->g = $this->g1 + $this->g2 + $this->g3 + $this->g4 + $this->g5 + $this->g6 + $this->g7 + $this->g8;
    	
    	//DAVČNO PRIZNANI ODHODKI (5 – 6 + 7) 
    	$this->h = $this->e - $this->f + $this->g;
    	
    	//RAZLIKA med davčno priznanimi prihodki in odhodki (4 – 8)
    	$this->i = $this->d - $this->h > 0 ? $this->d - $this->h : 0;
    	
    	//RAZLIKA med davčno priznanimi odhodki in prihodki (8 – 4)
    	$this->j = $this->h - $this->d > 0 ? $this->h - $this->d : 0;
    	
    	$this->k = $this->k1 - $this->k2 + $this->k3 - $this->k4;
    	
    	$this->l = $this->l1 + $this->l2 + $this->l3;
    	
    	//DAVČNA OSNOVA (9 + 11 + 12) ali (11+12-10); če je > 0
    	$interm = $this->i>0?($this->i + $this->k + $this->l):($this->k + $this->l - $this->j);
    	$this->m = $interm > 0 ? $interm : 0;
    	
    	//DAVČNA IZGUBA (9 + 11 + 12) ali (11+12-10); če je < 0
    	$this->n = $interm < 0 ? $interm : 0;
    	
    	//Zmanjšanje davčne osnove in davčne olajšave (vsota [(vsota 15.1. do 15.16 brez 15.13, vendar največ do višine 0 % davčne osnove iz zaporedne št. 13) in (15.13)], vendar največ do višine davčne osnove iz zaporedne št. 13)
    	$this->o = $this->o1 + $this->o2 + $this->o3 + $this->o4 + $this->o5 + $this->o6 + $this->o7 + $this->o8 + $this->o9 + $this->o10 +
    	$this->o11 + $this->o12 + $this->o13 + $this->o14 + $this->o15 + $this->o16;
    	
    	//OSNOVA ZA DOHODNINO (izračun dohodnine na letni ravni) (13-15)
    	$this->p = $this->m - $this->o;   
    	
    	// 	Olajšave, ki zmanjšujejo osnovo za dohodnino (17.1+17.2+17.3)
    	$this->q = $this->q1 + $this->q2 +$this->q3;
    	
    	//19. 	OSNOVA ZA AKONTACIJO DOHODNINE (16-17); če je > 0
    	$this->s = $this->p - $this->q > 0 ? $this->p - $this->q : 0;  
    	    	
    	//This should be revised every year.
    	$taxLevels = [8021.34, 20400, 48000, 70907.2]; 
    	$fixedTaxes = [1238.41, 4625.65, 14009.65, 22943.46];
    	$taxPercentages = [0.16, 0.27, 0.34, 0.39, 0.5];
    	    	
    	$incomeTax = $this->CalculateIncomeTax($this->s, $taxPercentages, $taxLevels, $fixedTaxes);
    	$this->t1 = $incomeTax[0];
    	$this->t1low = $incomeTax[1];
    	$this->t1high = $incomeTax[2];
    	$this->t1percent = $incomeTax[3];
    	$this->t2 = $incomeTax[4];
    	$this->t2low = $incomeTax[5];
    	$this->t2high = $incomeTax[6];
    	$this->t2percent = $incomeTax[7];
    	$this->t = $this->t1 + $this->t2;
    	
    	//DAVČNA OBVEZNOST (20-21+22) 
    	$this->w = $this->t - $this->u + $this->v;
    	
    	$interm = $this->w - $this->x - $this->y;
    	//OBVEZNOST ZA DOPLAČILO AKONTACIJE (23 – 24 – 25), če je > 0 
    	$this->z = $interm > 0 ? $interm : 0;
    	//PREVEČ OBRAČUNANA PREDHODNA AKONTACIJA (23 – 24 – 25 ), če je < 0 
    	$this->aa = $interm < 0 ? -$interm : 0;
    	
    	//ToDo: Calculate based on timespan (if shorter than full year)
    	//OSNOVA ZA DOLOČITEV PREDHODNE AKONTACIJE ali akontacije dohodnine 
    	$this->ab = $this->s;
    	
    	$nxtYearIncomeTax = $this->CalculateIncomeTax($this->ab, $taxPercentages, $taxLevels, $fixedTaxes);
    	$this->ac = $nxtYearIncomeTax[0] + $nxtYearIncomeTax[4];
    	
    	$this->ad = $this->ac > 400 ? $this->ac / 12 : 0;
    	
    	$this->ae = $this->ac <= 400 ? $this->ac / 4 : 0;
    	
    }
    	
    private function CalculateIncomeTax(float $baseIncome, array $taxPercentages, array $taxLevels, array $fixedTaxes):array
    {
    	$incomeTax = [0, 0, 0, 0, 0, 0, 0, 0];
    	$incomeTax[0] = 0;
    	$incomeTax[4] = $baseIncome*$taxPercentages[0];    	
    	$incomeTax[6] = $baseIncome;
    	$incomeTax[7] = $taxPercentages[0];
    	if ($baseIncome > $taxLevels[0])
    	{
    		$incomeTax[0] = $fixedTaxes[0];
    		$incomeTax[2] = $taxLevels[0];
    		$incomeTax[4] = ($baseIncome-$taxLevels[0])*$taxPercentages[1];
    		$incomeTax[5] = $taxLevels[0];
    		$incomeTax[6] = $baseIncome;
    		$incomeTax[7] = $taxPercentages[1];
    	}
    	if ($baseIncome > $taxLevels[1])
    	{
    		$incomeTax[0] = $fixedTaxes[1];
    		$incomeTax[2] = $taxLevels[1];
    		$incomeTax[4] = ($baseIncome-$taxLevels[1])*$taxPercentages[2];
    		$incomeTax[5] = $taxLevels[1];
    		$incomeTax[6] = $baseIncome;
    		$incomeTax[7] = $taxPercentages[2];
    	}
    	if ($baseIncome > $taxLevels[2])
    	{
    		$incomeTax[0] = $fixedTaxes[2];
    		$incomeTax[2] = $taxLevels[2];
    		$incomeTax[4] = ($baseIncome-$taxLevels[2])*$taxPercentages[3];
    		$incomeTax[5] = $taxLevels[2];
    		$incomeTax[6] = $baseIncome;
    		$incomeTax[7] = $taxPercentages[3];
    	}
    	if ($baseIncome > $taxLevels[3])
    	{
    		$incomeTax[0] = $fixedTaxes[3];
    		$incomeTax[2] = $taxLevels[3];
    		$incomeTax[4] = ($baseIncome-$taxLevels[3])*$taxPercentages[4];
    		$incomeTax[5] = $taxLevels[3];
    		$incomeTax[6] = $baseIncome;
    		$incomeTax[7] = $taxPercentages[4];
    	}
    	return $incomeTax;
    }
}
