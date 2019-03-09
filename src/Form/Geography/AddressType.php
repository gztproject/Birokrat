<?php 
namespace App\Form\Geography;

use App\Entity\Geography\Post;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('line1', TextType::class,[
                'label' => 'label.line1'
            ])
            ->add('line2', TextType::class,[
            		'label' => 'label.line2',
            		'required' => false
            ])
            ->add('post', EntityType::class, array(
            		'class' => Post::class,
            		'choice_label' => 'name',
            		'expanded'=>false,
            		'multiple'=>false,
            		'label' => 'label.post',
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        		'data_class' => AddressDTO::class,
        ));
    }
}
class AddressDTO
{
	private $line1;
	private $line2;
	private $post;
	
	public function getLine1(): ?string
	{
		return $this->line1;
	}
	
	public function setLine1(string $line1): self
	{
		$this->line1 = $line1;
		
		return $this;
	}
	
	public function getLine2(): ?string
	{
		return $this->line2;
	}
	
	public function setLine2(?string $line2): self
	{
		$this->line2 = $line2;
		
		return $this;
	}
	
	public function getPost(): ?Post
	{
		return $this->post;
	}
	
	public function setPost(?Post $post): self
	{
		$this->post = $post;
		
		return $this;
	}
}
